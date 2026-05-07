<?php
session_start();
include('includes/db_connect.php');

// Redirect if not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch logged-in student - FIXED: Use the primary key ID from session
$student_db_id = $_SESSION['student_id']; // This is now 24 (primary key)
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?"); // CHANGED: id instead of student_id
$stmt->execute([$student_db_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Student number for display
$student_number = $student['student_id']; // This is 5555555555
// Fetch submissions for this student - FIXED: Use primary key 'id'
$submissions = $conn->prepare("SELECT * FROM submissions WHERE student_id = ? ORDER BY created_at DESC");
$submissions->execute([$student_db_id]); // This should be the primary key 'id'

// And for display:
$submissions_display = $conn->prepare("SELECT * FROM submissions WHERE student_id = ? ORDER BY created_at DESC");
$submissions_display->execute([$student_db_id]);
// Fetch uploaded files for this student
$fileStmt = $conn->prepare("SELECT * FROM files WHERE student_id = ?");
$fileStmt->execute([$student_db_id]);
$file = $fileStmt->fetch(PDO::FETCH_ASSOC);

// Check if evaluation already uploaded
$evalCheck = $conn->prepare("SELECT COUNT(*) FROM files WHERE student_id = ? AND eval_form IS NOT NULL");
$evalCheck->execute([$student_db_id]);
$evalUploaded = ($evalCheck->fetchColumn() > 0) ? 'true' : 'false';

// =========================================================
// Handle file deletion
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $deleteFile = basename($_POST['delete_file']);
    $filePath = "uploads/evaluations/" . $deleteFile;

    // Find which column to nullify
    $columns = ['eval_form', 'shiftee_form', 'loi_form'];
    foreach ($columns as $col) {
        $checkCol = $conn->prepare("SELECT $col FROM files WHERE student_id = ? AND $col = ?");
        $checkCol->execute([$student_db_id, $deleteFile]);
        if ($checkCol->fetch()) {
            if (file_exists($filePath)) unlink($filePath);
            $update = $conn->prepare("UPDATE files SET $col = NULL WHERE student_id = ?");
            $update->execute([$student_db_id]);
            break;
        }
    }

    header("Location: dashboard.php");
    exit();
}
// =========================================================
// Handle submission deletion for irregular students
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission'])) {
    $submission_id = intval($_POST['delete_submission']);
    
    try {
        $conn->beginTransaction();
        
        // 1. First, check if submission exists and belongs to this student
        $checkStmt = $conn->prepare("
            SELECT submission_id, reference_id, created_at 
            FROM submissions 
            WHERE submission_id = ? AND student_id = ?
        ");
        $checkStmt->execute([$submission_id, $student_db_id]);
        
        $submission = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$submission) {
            throw new Exception("Submission not found or access denied");
        }
        
        // 2. Get current semester to identify which courses were in this submission
        $current_semester_stmt = $conn->prepare("
            SELECT setting_value FROM system_settings 
            WHERE setting_key = 'current_semester'
        ");
        $current_semester_stmt->execute();
        $current_semester = $current_semester_stmt->fetchColumn();
        
        // 3. Find enrollments created around the same time as the submission
        $submission_time = $submission['created_at'];
        $time_window_start = date('Y-m-d H:i:s', strtotime($submission_time . ' -1 hour'));
        $time_window_end = date('Y-m-d H:i:s', strtotime($submission_time . ' +1 hour'));
        
        $enrollmentStmt = $conn->prepare("
            SELECT se.enrollment_id, se.section_course_id, sc.course_id
            FROM student_enrollments se
            JOIN section_courses sc ON se.section_course_id = sc.section_course_id
            JOIN courses c ON sc.course_id = c.course_id
            WHERE se.student_id = ? 
            AND se.enrolled_at BETWEEN ? AND ?
            AND c.semester = ?
        ");
        $enrollmentStmt->execute([$student_db_id, $time_window_start, $time_window_end, $current_semester]);
        $enrollments = $enrollmentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Process each enrollment from this submission
        foreach ($enrollments as $enrollment) {
            // Decrement section enrollment count
            $updateSection = $conn->prepare("
                UPDATE section_courses 
                SET current_enrollment = GREATEST(0, current_enrollment - 1)
                WHERE section_course_id = ?
            ");
            $updateSection->execute([$enrollment['section_course_id']]);
            
            // Update student_subjects status to 'available' instead of deleting
            // This preserves the record but makes the subject available again
            $updateSubjectStmt = $conn->prepare("
                UPDATE student_subjects 
                SET status = 'Not_Enrolled', enrolled_at = NULL
                WHERE student_id = ? AND course_id = ? AND status = 'enrolled'
            ");
            $updateSubjectStmt->execute([$student_db_id, $enrollment['course_id']]);
        }
        
        // 5. Delete the enrollments from this submission
        $deleteEnrollments = $conn->prepare("
            DELETE se FROM student_enrollments se
            JOIN section_courses sc ON se.section_course_id = sc.section_course_id
            JOIN courses c ON sc.course_id = c.course_id
            WHERE se.student_id = ? 
            AND se.enrolled_at BETWEEN ? AND ?
            AND c.semester = ?
        ");
        $deleteEnrollments->execute([$student_db_id, $time_window_start, $time_window_end, $current_semester]);
        
        // 6. Delete the submission record
        $deleteSubmission = $conn->prepare("
            DELETE FROM submissions 
            WHERE submission_id = ?
        ");
        $deleteSubmission->execute([$submission_id]);
        
        $conn->commit();
        
        // Clear any temporary session data
        if (isset($_SESSION['temp_enrollments'])) {
            unset($_SESSION['temp_enrollments']);
        }
        
        header("Location: dashboard.php?success=submission_deleted");
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error deleting submission: " . $e->getMessage());
        header("Location: dashboard.php?error=delete_failed&message=" . urlencode($e->getMessage()));
        exit();
    }
}

// =========================================================
// Handle evaluation upload via POST
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['evaluation_form'])) {
    $file = $_FILES['evaluation_form'];
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];

    if ($file['error'] === 0 && in_array($file['type'], $allowedTypes)) {
        $uploadDir = 'uploads/evaluations/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $originalName); 
        $filename = "{$student_number}_" . time() . "_{$cleanName}.{$extension}"; // CHANGED: Use student_number for filename

        $targetFile = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // Update file record
            $stmt = $conn->prepare("UPDATE files SET eval_form = ? WHERE student_id = ?");
            $stmt->execute([$filename, $student_db_id]);

            // === Run Python script to extract and insert subjects ===
            $python  = "C:\\Users\\gablo\\AppData\\Local\\Programs\\Python\\Python311\\python.exe";
            $script  = "C:\\xampp\\htdocs\\E-RIS\\extract_courses.py";
            $pdfPath = realpath($targetFile);
            $cmd = escapeshellcmd("$python \"$script\" \"$pdfPath\" $student_db_id");
            $output = shell_exec($cmd);

            file_put_contents('uploads/evaluations/python_log.txt', date('Y-m-d H:i:s') . " - " . $output . PHP_EOL, FILE_APPEND);

            $evalUploaded = 'true';
            header("Location: submit_form.php");
            exit();
        } else {
            $uploadError = 'Failed to move uploaded file.';
        }
    } else {
        $uploadError = 'Only PDF, JPG, or PNG files are allowed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-RIS | Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.navbar { background-color: #800000 !important; }
.navbar-brand, .nav-link, .navbar-text { color: #fff !important; }
.card { border-radius: 10px; }
.text-maroon { color: #800000 !important; }
.status-badge { padding: 0.4em 0.8em; border-radius: 10px; font-size: 0.85rem; font-weight: 600; }
.status-pending { background-color: #fff3cd; color: #856404; }
.status-approved { background-color: #d4edda; color: #155724; }
.status-denied { background-color: #f8d7da; color: #721c24; }
.bg-maroon { background-color: #800000 !important; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow">
<div class="container">
    <a class="navbar-brand d-flex align-items-center fw-bold" href="#">
        <img src="assets/img/vid.jpg" alt="E-RIS Logo" width="40" height="40" class="me-2 rounded-circle">
        E-RIS
    </a>
    <div class="ms-auto">
        <span class="navbar-text me-3">
        Welcome, <strong><?php echo htmlspecialchars($student['first_name']); ?></strong>
        </span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">
        <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>
</nav>

<div class="container py-5">
<div class="row justify-content-center">
<div class="col-md-10">

<!-- Student Info Card -->
<div class="card shadow-sm mb-4 p-4 border-0">
<div class="mb-4 text-left">
<div class="text-maroon fw-bold small mb-1" style="letter-spacing: 1.5px;">STUDENT</div>
<h4 class="fw-bold text-maroon m-0 d-flex justify-content-left align-items-left gap-2">
<i class="bi bi-person-circle fs-2"></i>
<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
</h4>
</div>
<div class="row g-3">
<?php
$infoFields = [
    ['icon' => 'bi-card-text', 'label' => 'Student ID', 'value' => $student['student_id']],
    ['icon' => 'bi-envelope', 'label' => 'Email', 'value' => $student['email']],
    ['icon' => 'bi-building', 'label' => 'Major', 'value' => $student['major']],
    ['icon' => 'bi-phone', 'label' => 'Mobile', 'value' => $student['mobile_number']],
    ['icon' => 'bi-calendar', 'label' => 'Year Level', 'value' => $student['year_level']],
    ['icon' => 'bi-person-badge', 'label' => 'Status', 'value'=> $student['status']]
];
foreach($infoFields as $f): ?>
<div class="col-md-6">
<div class="p-3 border rounded d-flex align-items-center gap-2 shadow-sm">
<i class="bi <?php echo $f['icon']; ?> text-maroon fs-5"></i>
<div>
<div class="small text-muted"><?php echo $f['label']; ?></div>
<strong><?php echo htmlspecialchars($f['value']); ?></strong>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>

<!-- Uploaded Files Card -->
<div class="card shadow-sm mb-4 p-4 border-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-maroon mb-0">
            <i class="bi bi-folder-check me-2"></i>Submitted Files
        </h5>
    </div>

    <?php if ($file && ($file['eval_form'] || $file['shiftee_form'] || $file['loi_form'])): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>File Type</th>
                        <th>File Name</th>
                        <th>Date Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $uploads = [
                        'Evaluation Form' => $file['eval_form'],
                        'Shiftee Form' => $file['shiftee_form'],
                        'Letter of Intent' => $file['loi_form']
                    ];
                    foreach ($uploads as $label => $filename):
                        if ($filename):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($label); ?></td>
                        <td><?php echo htmlspecialchars($filename); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($file['created_at'])); ?></td>
                        <td>
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm view-file-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#fileViewModal"
                                    data-filepath="uploads/evaluations/<?php echo rawurlencode($filename); ?>">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button type="button"
        class="btn btn-outline-danger btn-sm delete-btn"
        data-filename="<?php echo htmlspecialchars($filename); ?>"
        data-bs-toggle="modal"
        data-bs-target="#confirmDeleteModal">
    <i class="bi bi-trash"></i> Delete
</button>

                        </td>
                    </tr>
                    <?php endif; endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center text-muted py-3">
            <i class="bi bi-inbox me-2"></i>No files uploaded yet.
        </div>
    <?php endif; ?>
</div>

<!-- Submissions Card -->
<div class="card shadow p-4">
<div class="d-flex justify-content-between align-items-center mb-3">
<h5 class="fw-bold text-maroon mb-0"><i class="bi bi-folder2-open me-2"></i>My Submissions</h5>
<button id="startSubmissionBtn" class="btn btn-primary btn-sm">
<i class="bi bi-plus-lg"></i> Start New Submission
</button>
</div>

<?php if ($submissions->rowCount() > 0): ?>
<div class="table-responsive">
<table class="table table-striped align-middle">
<thead>
<tr>
<th>Reference ID</th>
<th>Type</th>
<th>Status</th>
<th>Date Submitted</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php 
// Re-fetch submissions for display
$submissions_display = $conn->prepare("SELECT * FROM submissions WHERE student_id = ? ORDER BY created_at DESC");
$submissions_display->execute([$student_db_id]);
while ($sub = $submissions_display->fetch(PDO::FETCH_ASSOC)): ?>
<tr>
<td><?php echo htmlspecialchars($sub['reference_id'] ?? 'N/A'); ?></td>
<td><?php echo htmlspecialchars($sub['type'] ?? '-'); ?></td>
<td>
<?php
$status = strtolower($sub['status'] ?? 'pending');
$badgeClass = $status === 'approved' ? 'status-approved' :
              ($status === 'denied' ? 'status-denied' : 'status-pending');
?>
<span class="status-badge <?php echo $badgeClass; ?>">
<?php echo ucfirst($status); ?>
</span>
</td>
<td><?php echo date("M d, Y h:i A", strtotime($sub['created_at'])); ?></td>
<td>
    <button type="button" 
            class="btn btn-outline-primary btn-sm view-pdf-btn"
            data-submission-id="<?php echo $sub['submission_id']; ?>"
            data-reference-id="<?php echo htmlspecialchars($sub['reference_id']); ?>"
            data-status="<?php echo htmlspecialchars($sub['status']); ?>"
            data-bs-toggle="modal"
            data-bs-target="#pdfViewModal">
        <i class="bi bi-eye"></i> View
    </button>
    <button type="button" 
            class="btn btn-outline-danger btn-sm delete-submission-btn"
            data-submission-id="<?php echo $sub['submission_id']; ?>"
            data-bs-toggle="modal"
            data-bs-target="#confirmDeleteSubmissionModal">
        <i class="bi bi-trash"></i> Delete
    </button>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="text-center text-muted py-3">
<i class="bi bi-inbox me-2"></i>No submissions found.
</div>
<?php endif; ?>
</div>

</div>
</div>
</div>
<!-- PDF View Modal -->
<div class="modal fade" id="pdfViewModal" tabindex="-1" aria-labelledby="pdfViewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-maroon text-white">
        <h5 class="modal-title" id="pdfViewModalLabel">
          <i class="bi bi-file-earmark-pdf me-2"></i>Advising Form
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" style="height: 80vh;">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
          <div>
            <strong>Reference ID:</strong> <span id="pdfReferenceId"></span> | 
            <strong>Status:</strong> <span id="pdfStatus"></span>
          </div>
          <a href="#" id="pdfDownloadLink" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-download me-1"></i>Download
          </a>
        </div>
        <iframe id="pdfViewerFrame" src="" style="width:100%; height:calc(100% - 60px); border:none;"></iframe>
      </div>
    </div>
  </div>
</div>
<!-- Evaluation Form Modal -->
<div class="modal fade" id="evalModal" tabindex="-1" aria-labelledby="evalModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content border-0 shadow rounded-4">
<div class="modal-header bg-maroon text-white">
<h5 class="modal-title" id="evalModalLabel"><i class="bi bi-file-earmark-arrow-up me-2"></i>Upload Evaluation Form</h5>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<form method="POST" enctype="multipart/form-data">
<div class="mb-3">
<label for="evaluation_form" class="form-label">Choose your official Evaluation form. It will be used in your enrollment so make sure to use the official form.</label>
<input class="form-control" type="file" id="evaluation_form" name="evaluation_form" required>
</div>
<div class="text-end">
<button type="submit" class="btn btn-primary">Upload & Continue</button>
</div>
</form>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
</div>
</div>
</div>
</div>

<!-- File Viewer Modal -->
<div class="modal fade" id="fileViewModal" tabindex="-1" aria-labelledby="fileViewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-maroon text-white">
        <h5 class="modal-title" id="fileViewModalLabel"><i class="bi bi-file-earmark-text me-2"></i>View File</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center" style="height:80vh;">
        <iframe id="fileViewerFrame" src="" style="width:100%; height:100%; border:none;" allowfullscreen></iframe>
      </div>
    </div>
  </div>
</div>

<!-- Delete File Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeleteModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm File Deletion
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <p class="mb-3">Are you sure you want to delete this file?</p>
        <form id="deleteFileForm" method="POST">
          <input type="hidden" name="delete_file" id="deleteFileInput">
          <div class="d-flex justify-content-center gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete File</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Submission Confirmation Modal -->
<div class="modal fade" id="confirmDeleteSubmissionModal" tabindex="-1" aria-labelledby="confirmDeleteSubmissionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeleteSubmissionModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Submission Deletion
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <p class="mb-3"><strong>Warning:</strong> This will permanently delete this submission and all associated enrollments. This action cannot be undone.</p>
        <form id="deleteSubmissionForm" method="POST">
          <input type="hidden" name="delete_submission" id="deleteSubmissionInput">
          <div class="d-flex justify-content-center gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete Submission</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // File viewer functionality
    const modal = document.getElementById('fileViewModal');
    const iframe = document.getElementById('fileViewerFrame');

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const filePath = button.getAttribute('data-filepath');
        iframe.src = filePath;
    });

    modal.addEventListener('hidden.bs.modal', function () {
        iframe.src = '';
    });

    // Evaluation modal functionality
    const evalUploaded = <?php echo ($evalUploaded === 'true' ? 'true' : 'false'); ?>;
    const btn = document.getElementById('startSubmissionBtn');
    const modalEl = document.getElementById('evalModal');
    const evalModal = new bootstrap.Modal(modalEl);

    btn.addEventListener('click', function() {
        if(!evalUploaded) {
            evalModal.show();
        } else {
            window.location.href = 'submit_form.php';
        }
    });

    // File deletion functionality
    const deleteFileButtons = document.querySelectorAll('.delete-btn');
    const deleteFileInput = document.getElementById('deleteFileInput');

    deleteFileButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const filename = btn.getAttribute('data-filename');
            deleteFileInput.value = filename;
        });
    });

    // Submission deletion functionality
    const deleteSubmissionButtons = document.querySelectorAll('.delete-submission-btn');
    const deleteSubmissionInput = document.getElementById('deleteSubmissionInput');

    deleteSubmissionButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const submissionId = btn.getAttribute('data-submission-id');
            deleteSubmissionInput.value = submissionId;
        });
    });

    // Clear inputs when modals close
    const deleteFileModal = document.getElementById('confirmDeleteModal');
    const deleteSubmissionModal = document.getElementById('confirmDeleteSubmissionModal');
    
    deleteFileModal.addEventListener('hidden.bs.modal', () => {
        deleteFileInput.value = '';
    });
    
    deleteSubmissionModal.addEventListener('hidden.bs.modal', () => {
        deleteSubmissionInput.value = '';
    });
});

// PDF View functionality
const pdfViewButtons = document.querySelectorAll('.view-pdf-btn');
const pdfViewerFrame = document.getElementById('pdfViewerFrame');
const pdfReferenceId = document.getElementById('pdfReferenceId');
const pdfStatus = document.getElementById('pdfStatus');
const pdfDownloadLink = document.getElementById('pdfDownloadLink');

pdfViewButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        const submissionId = btn.getAttribute('data-submission-id');
        const referenceId = btn.getAttribute('data-reference-id');
        const status = btn.getAttribute('data-status');
        
        // Update modal header info
        pdfReferenceId.textContent = referenceId;
        pdfStatus.textContent = status;
        
        // Set PDF source
        const pdfUrl = `view_pdf.php?id=${submissionId}`;
        pdfViewerFrame.src = pdfUrl;
        
        // Set download link
        pdfDownloadLink.href = pdfUrl + '&download=1';
    });
});

// Clear PDF iframe when modal closes
const pdfModal = document.getElementById('pdfViewModal');
pdfModal.addEventListener('hidden.bs.modal', () => {
    pdfViewerFrame.src = '';
});

</script>

</body>
</html>