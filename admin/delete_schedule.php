<?php
/** @var PDO $conn */
include('../includes/db_connect.php');
include('../includes/auth_admin.php');

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_text'])) {
    $semester = intval($_POST['semester']);
    $confirmText = trim($_POST['confirm_text']);

    // Safety Lock
    if ($confirmText !== 'DELETE') {
        $message = "Action aborted. You must type DELETE exactly as shown to confirm.";
        $messageType = "danger";
    } else {
        try {
            $conn->beginTransaction();

            // 1. Remove student enrollments for this semester's courses to prevent #1451 Foreign Key error
            $stmt1 = $conn->prepare("
                DELETE se FROM student_enrollments se
                JOIN section_courses sc ON se.section_course_id = sc.section_course_id
                WHERE sc.semester = ?
            ");
            $stmt1->execute([$semester]);
            $deletedEnrollments = $stmt1->rowCount();

            // 2. Remove the actual schedule time slots
            $stmt2 = $conn->prepare("
                DELETE scs FROM section_course_schedules scs
                JOIN section_courses sc ON scs.section_course_id = sc.section_course_id
                WHERE sc.semester = ?
            ");
            $stmt2->execute([$semester]);
            $deletedSchedules = $stmt2->rowCount();

            // 3. Remove the section-course links
            $stmt3 = $conn->prepare("DELETE FROM section_courses WHERE semester = ?");
            $stmt3->execute([$semester]);
            $deletedCourses = $stmt3->rowCount();

            // 4. Remove the sections themselves (e.g. BSIT 1-A)
            $stmt4 = $conn->prepare("DELETE FROM sections WHERE semester = ?");
            $stmt4->execute([$semester]);
            $deletedSections = $stmt4->rowCount();

            $conn->commit();

            $semesterText = ($semester == 1) ? "1st Semester" : "2nd Semester";
            $message = "<strong>Success!</strong> All schedule data for the $semesterText has been wiped clean.<br>
                        <ul class='mb-0 mt-2'>
                            <li>Removed $deletedSchedules schedule slots</li>
                            <li>Removed $deletedCourses course assignments</li>
                            <li>Removed $deletedSections sections</li>
                            <li>Cleared $deletedEnrollments associated student enrollments</li>
                        </ul>";
            $messageType = "success";

        } catch (Exception $e) {
            $conn->rollBack();
            $message = "Database error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-RIS | Delete Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background-color: #800000 !important; }
        .text-maroon { color: #800000 !important; }
        .card { border-radius: 10px; }
        .btn-maroon { background-color: #800000; color: white; }
        .btn-maroon:hover { background-color: #600000; color: white; }
        .danger-zone { border: 2px dashed #dc3545; border-radius: 10px; padding: 30px; background-color: #fff5f5; }
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
                    <a href="import_schedule.php" class="list-group-item list-group-item-action"><i class="bi bi-upload me-2"></i>Import Schedule</a>
                    <a href="delete_schedule.php" class="list-group-item list-group-item-action active"><i class="bi bi-trash me-2"></i>Delete Schedule</a>
                    <a href="view_submissions.php" class="list-group-item list-group-item-action"><i class="bi bi-folder me-2"></i>View Submissions</a>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm p-4 border-danger">
                <div class="d-flex align-items-center mb-3 text-danger">
                    <i class="bi bi-exclamation-triangle-fill fs-2 me-3"></i>
                    <div>
                        <h4 class="fw-bold mb-0">Danger Zone: Wipe Semester Schedule</h4>
                        <p class="text-muted small mb-0">This action is permanent and cannot be undone.</p>
                    </div>
                </div>

                <div class="danger-zone">
                    <p class="mb-4 text-dark">
                        Using this tool will permanently delete all uploaded class schedules, course assignments, sections, and student enrollments tied to the selected semester. It will <strong>not</strong> delete the actual courses from the master database.
                    </p>

                    <form method="POST" id="deleteForm">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Select Semester to Wipe</label>
                                <select name="semester" class="form-select border-danger">
                                    <option value="1">1st Semester</option>
                                    <option value="2" selected>2nd Semester</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Type <span class="text-danger">DELETE</span> to confirm</label>
                                <input type="text" name="confirm_text" class="form-control border-danger" placeholder="DELETE" required autocomplete="off">
                            </div>
                        </div>

                        <button type="submit" name="delete_semester" class="btn btn-danger btn-lg w-100" id="deleteBtn">
                            <i class="bi bi-trash3-fill me-2"></i> Permanently Wipe Schedule
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('deleteForm').addEventListener('submit', function (e) {
    const confirmInput = document.querySelector('input[name="confirm_text"]').value;
    if (confirmInput !== 'DELETE') {
        e.preventDefault();
        alert('You must type DELETE in all caps to confirm this action.');
    } else {
        const btn = document.getElementById('deleteBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Wiping Data...';
    }
});
</script>
</body>
</html>