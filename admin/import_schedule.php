<?php
/** @var PDO $conn */
include('../includes/db_connect.php');
include('../includes/auth_admin.php');

$results = [];
$errors  = [];

// ─────────────────────────────────────────────
//  POST: handle the uploaded file
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['schedule_file'])) {

    $file = $_FILES['schedule_file'];

    // Basic validation
    $allowed = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload error: ' . $file['error'];
    } elseif ($ext !== 'xlsx') {
        $errors[] = 'Only .xlsx files are accepted.';
    } else {

        // Save to temp location
        $tmpPath = sys_get_temp_dir() . '/' . uniqid('sched_') . '.xlsx';
        move_uploaded_file($file['tmp_name'], $tmpPath);

        // Run the Python parser and capture JSON output
        $escaped = escapeshellarg($tmpPath);
        $output  = shell_exec("python " . escapeshellarg(__DIR__ . '/schedule_parser.py') . " $escaped 2>&1");

        @unlink($tmpPath);

        if (!$output) {
            $errors[] = 'Parser returned no output. Ensure Python and openpyxl are installed.';
        } else {
            $data = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $errors[] = 'Parser error: ' . htmlspecialchars($output);
            } else {
                // ── Import data into database ──────────────────────────────
                try {
                    $conn->beginTransaction();

                    $importMode = $_POST['import_mode'] ?? 'merge';
                    $semester   = intval($_POST['semester'] ?? 2);

                    if ($importMode === 'replace') {
                        $conn->prepare("
                            DELETE scs FROM section_course_schedules scs
                            JOIN section_courses sc ON scs.section_course_id = sc.section_course_id
                            WHERE sc.semester = ?
                        ")->execute([$semester]);

                        $conn->prepare("DELETE FROM section_courses WHERE semester = ?")->execute([$semester]);
                        $conn->prepare("DELETE FROM sections WHERE semester = ?")->execute([$semester]);
                    }

                    $stats = ['sections' => 0, 'courses' => 0, 'schedules' => 0, 'skipped' => 0, 'new_courses' => 0];

                    foreach ($data as $sheetData) {
                        $sectionName = $sheetData['section'];
                        $major       = $sheetData['major'];
                        $yearLevel   = $sheetData['year_level'];
                        $entries     = $sheetData['schedule_entries'];
                        $courseList  = $sheetData['course_list'];

                        // 1. Ensure section exists
                        $stmtSec = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND semester = ?");
                        $stmtSec->execute([$sectionName, $semester]);
                        $secRow = $stmtSec->fetch(PDO::FETCH_ASSOC);

                        if ($secRow) {
                            $sectionId = $secRow['section_id'];
                        } else {
                            $conn->prepare("INSERT INTO sections (section_name, year_level, semester, created_at) VALUES (?, ?, ?, NOW())")
                                 ->execute([$sectionName, $yearLevel, $semester]);
                            $sectionId = $conn->lastInsertId();
                            $stats['sections']++;
                        }

                        $processedCombos = [];

                        // 2. Process each schedule entry
                        foreach ($entries as $entry) {
                            $code       = $entry['course_code'];
                            $room       = $entry['room'];
                            $day        = $entry['day'];
                            $startTime  = $entry['start_time'];
                            $endTime    = $entry['end_time'];

                            // Get course title and units from the footer list
                            $title = $code;
                            $units = 3;
                            foreach ($courseList as $c) {
                                if ($c['code'] === $code) {
                                    $title = $c['title'];
                                    $units = $c['units'];
                                    break;
                                }
                            }

                            // 3. Ensure course exists (Standard Exact Match)
                            $stmtCourse = $conn->prepare("SELECT course_id FROM courses WHERE course_code = ?");
                            $stmtCourse->execute([$code]);
                            $courseRow = $stmtCourse->fetch(PDO::FETCH_ASSOC);

                            if ($courseRow) {
                                $courseId = $courseRow['course_id'];
                            } else {
                                $lecUnits = is_numeric($units) ? (float)$units : 3;
                                $conn->prepare("INSERT INTO courses (course_code, course_title, units, year_level, semester, major, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
                                     ->execute([$code, $title, $lecUnits, $yearLevel, $semester, $major]);
                                $courseId = $conn->lastInsertId();
                                $stats['new_courses']++;
                            }

                            // 4. Link section and course
                            $comboKey = $sectionId . '_' . $courseId;
                            if (isset($processedCombos[$comboKey])) {
                                $scId = $processedCombos[$comboKey];
                            } else {
                                $stmtSC = $conn->prepare("SELECT section_course_id FROM section_courses WHERE section_id = ? AND course_id = ? AND semester = ?");
                                $stmtSC->execute([$sectionId, $courseId, $semester]);
                                $scRow = $stmtSC->fetch(PDO::FETCH_ASSOC);

                                if ($scRow) {
                                    $scId = $scRow['section_course_id'];
                                } else {
                                    $conn->prepare("INSERT INTO section_courses (section_id, course_id, semester, max_capacity, current_enrollment, created_at) VALUES (?, ?, ?, 40, 0, NOW())")
                                         ->execute([$sectionId, $courseId, $semester]);
                                    $scId = $conn->lastInsertId();
                                    $stats['courses']++;
                                }
                                $processedCombos[$comboKey] = $scId;
                            }

                            // 5. Add Schedule Slot
                            $stmtChk = $conn->prepare("SELECT COUNT(*) FROM section_course_schedules WHERE section_course_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?");
                            $stmtChk->execute([$scId, $day, $startTime, $endTime]);

                            if ($stmtChk->fetchColumn() == 0) {
                                $conn->prepare("INSERT INTO section_course_schedules (section_course_id, day_of_week, start_time, end_time, room) VALUES (?, ?, ?, ?, ?)")
                                     ->execute([$scId, $day, $startTime, $endTime, $room]);
                                $stats['schedules']++;
                            } else {
                                $stats['skipped']++;
                            }
                        }
                    }

                    $conn->commit();
                    $results = $stats;
                    $results['sheets'] = count($data);

                } catch (Exception $e) {
                    $conn->rollBack();
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-RIS | Import Class Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background-color: #800000 !important; }
        .text-maroon { color: #800000 !important; }
        .card { border-radius: 10px; }
        .btn-maroon { background-color: #800000; color: white; }
        .btn-maroon:hover { background-color: #600000; color: white; }
        .upload-zone {
            border: 2px dashed #800000; border-radius: 10px; padding: 40px; text-align: center; cursor: pointer; transition: background .2s;
        }
        .upload-zone:hover { background: #fff5f5; }
        .stat-card { border-left: 4px solid #800000; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark shadow">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <img src="../assets/img/vid.jpg" width="40" height="40" class="me-2 rounded-circle"> E-RIS Admin
        </a>
        <div class="ms-auto text-white">
            Welcome, <strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></strong>
            <a href="logout.php" class="btn btn-outline-light btn-sm ms-3">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="row">
        <div class="col-md-3">
            <div class="card shadow-sm p-3">
                <h5 class="text-maroon fw-bold mb-3">Admin Menu</h5>
                <div class="list-group">
                    <a href="dashboard.php" class="list-group-item list-group-item-action"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                    <a href="manage_courses.php" class="list-group-item list-group-item-action"><i class="bi bi-book me-2"></i>Manage Courses</a>
                    <a href="import_schedule.php" class="list-group-item list-group-item-action active"><i class="bi bi-upload me-2"></i>Import Schedule</a>
                    <a href="view_submissions.php" class="list-group-item list-group-item-action"><i class="bi bi-folder me-2"></i>View Submissions</a>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <?php if (!empty($results)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Import successful!</strong> Processed <?= $results['sheets'] ?> sheet(s).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <div class="row g-3 mb-4">
                <?php
                $statItems = [
                    ['icon'=>'bi-grid-3x3-gap','label'=>'Sections added',     'val'=>$results['sections'],     'color'=>'primary'],
                    ['icon'=>'bi-book',         'label'=>'Section-courses',    'val'=>$results['courses'],      'color'=>'success'],
                    ['icon'=>'bi-calendar3',    'label'=>'Schedule slots',     'val'=>$results['schedules'],    'color'=>'info'],
                    ['icon'=>'bi-plus-circle',  'label'=>'New courses created','val'=>$results['new_courses'],  'color'=>'warning'],
                    ['icon'=>'bi-skip-forward', 'label'=>'Duplicates skipped', 'val'=>$results['skipped'],     'color'=>'secondary'],
                ];
                foreach ($statItems as $s): ?>
                <div class="col-6 col-md-4">
                    <div class="card shadow-sm p-3 stat-card">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi <?= $s['icon'] ?> text-<?= $s['color'] ?> fs-3"></i>
                            <div>
                                <div class="fw-bold fs-4"><?= $s['val'] ?></div>
                                <div class="small text-muted"><?= $s['label'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($err) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endforeach; ?>

            <div class="card shadow-sm p-4">
                <h4 class="text-maroon fw-bold mb-1"><i class="bi bi-cloud-upload me-2"></i>Import Official Class Schedule</h4>
                <p class="text-muted small mb-4">Upload the official XLSX class schedule file.</p>

                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-zone mb-4" id="dropZone" onclick="document.getElementById('schedule_file').click()">
                        <i class="bi bi-file-earmark-excel text-success" style="font-size:3rem;"></i>
                        <h5 class="mt-2 mb-1">Click to browse or drag & drop</h5>
                        <p class="text-muted small mb-0">Accepted: .xlsx files only</p>
                        <div id="fileName" class="mt-2 fw-bold text-maroon" style="display:none;"></div>
                    </div>
                    <input type="file" id="schedule_file" name="schedule_file" accept=".xlsx" class="d-none" required>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="1">1st Semester</option>
                                <option value="2" selected>2nd Semester</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Import Mode</label>
                            <select name="import_mode" class="form-select">
                                <option value="merge" selected>Merge</option>
                                <option value="replace">Replace (caution!)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-maroon btn-lg w-100" id="submitBtn">
                        <i class="bi bi-upload me-2"></i>Upload & Import Schedule
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('schedule_file').addEventListener('change', function () {
    const name = this.files[0]?.name;
    const el = document.getElementById('fileName');
    if (name) { el.textContent = '📄 ' + name; el.style.display = 'block'; }
});
const zone = document.getElementById('dropZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.background='#fff0f0'; });
zone.addEventListener('dragleave', () => zone.style.background='');
zone.addEventListener('drop', e => {
    e.preventDefault(); zone.style.background='';
    const file = e.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer(); dt.items.add(file);
        document.getElementById('schedule_file').files = dt.files;
        const el = document.getElementById('fileName');
        el.textContent = '📄 ' + file.name; el.style.display = 'block';
    }
});
document.getElementById('uploadForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';
});
</script>
</body>
</html>