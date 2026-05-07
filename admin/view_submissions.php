<?php
/** @var PDO $conn */
include('../includes/db_connect.php');
include('../includes/auth_admin.php');

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $submission_id = intval($_POST['submission_id']);
    $new_status = $_POST['status'];
    $feedback = $_POST['feedback'] ?? '';
    
    $allowed_statuses = ['pending', 'approved', 'denied'];
    if (in_array($new_status, $allowed_statuses)) {
        $stmt = $conn->prepare("UPDATE submissions SET status = ?, feedback = ? WHERE submission_id = ?");
        $stmt->execute([$new_status, $feedback, $submission_id]);
        $_SESSION['success'] = "Submission status updated successfully!";
    } else {
        $_SESSION['error'] = "Invalid status selected";
    }
    header("Location: view_submissions.php");
    exit();
}

// Get the current admin's username and determine which college they can access
$admin_username = $_SESSION['admin_username'];
$admin_role = $_SESSION['admin_role'];

// Map admin usernames to college programs based on major field
$college_access = [
    'bsbaadmin' => 'Bachelor of Science in Business Administration',
    'bshmadmin' => 'Bachelor of Science in Hospitality Management', 
    'beedadmin' => 'Bachelor of Elementary Education',
    'bsitadmin' => 'Bachelor of Science in Information Technology',
    'head_admin' => 'all' // Head admin can see all
];

// Map admin usernames to their display role names
$admin_roles = [
    'bsbaadmin' => 'BSBA Head Admin',
    'bshmadmin' => 'BSHM Head Admin',
    'beedadmin' => 'BEED Head Admin', 
    'bsitadmin' => 'BSIT Head Admin',
];

// Determine which college programs this admin can access
$allowed_college = $college_access[$admin_username] ?? ($admin_role === 'head_admin' ? 'all' : null);

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$student_type_filter = $_GET['student_type'] ?? 'all';
$college_filter = $_GET['college'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build query with filters and college restriction
$where_conditions = [];
$params = [];

// College access restriction (unless head admin)
if ($allowed_college !== 'all') {
    $where_conditions[] = "stu.major = ?";
    $params[] = $allowed_college;
} elseif ($college_filter !== 'all') {
    // Head admin can filter by college
    $where_conditions[] = "stu.major = ?";
    $params[] = $college_filter;
}

// Status filter
if ($status_filter !== 'all') {
    $where_conditions[] = "s.status = ?";
    $params[] = $status_filter;
}

// Student type filter (using status field from students table)
if ($student_type_filter !== 'all') {
    $where_conditions[] = "stu.status = ?";
    $params[] = $student_type_filter;
}

// Search filter
if (!empty($search_query)) {
    $where_conditions[] = "(stu.first_name LIKE ? OR stu.last_name LIKE ? OR stu.student_id LIKE ? OR s.reference_id LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get submissions with student info
$query = "
    SELECT 
        s.submission_id,
        s.reference_id,
        s.type,
        s.status,
        s.feedback,
        s.created_at,
        s.advising_form,
        s.student_id,
        stu.id as student_db_id,
        stu.student_id as student_number,
        stu.first_name,
        stu.middle_name,
        stu.last_name,
        stu.status as student_status,
        stu.major,
        stu.year_level
    FROM submissions s
    JOIN students stu ON s.student_id = stu.id
    $where_clause
    ORDER BY s.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Now get files separately for each submission to avoid duplication
$submission_files = [];
if (!empty($submissions)) {
    $student_ids = array_column($submissions, 'student_db_id');
    
    // Get files for all these students
    $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
    $files_query = "
        SELECT student_id, eval_form, shiftee_form, loi_form, signature_form 
        FROM files 
        WHERE student_id IN ($placeholders)
    ";
    $files_stmt = $conn->prepare($files_query);
    $files_stmt->execute($student_ids);
    $files_data = $files_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a lookup array for files by student_id
    foreach ($files_data as $file) {
        $submission_files[$file['student_id']] = $file;
    }
}

// Get counts for filters
$counts_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN s.status = 'denied' THEN 1 ELSE 0 END) as denied
    FROM submissions s
    JOIN students stu ON s.student_id = stu.id
    " . (!empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "")
;

$counts_stmt = $conn->prepare($counts_query);
$counts_stmt->execute($params);
$counts = $counts_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-RIS | View Submissions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background-color: #800000 !important; }
        .text-maroon { color: #800000 !important; }
        .card { border-radius: 10px; }
        .btn-outline-maroon {
            border: 1px solid #800000;
            color: #800000;
        }
        .btn-outline-maroon:hover {
            background-color: #800000;
            color: white;
        }
        .status-badge { padding: 0.4em 0.8em; border-radius: 10px; font-size: 0.85rem; font-weight: 600; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-denied { background-color: #f8d7da; color: #721c24; }
        .student-type-badge { padding: 0.3em 0.6em; border-radius: 8px; font-size: 0.75rem; }
        .student-regular { background-color: #e3f2fd; color: #1565c0; }
        .student-irregular { background-color: #fff3e0; color: #ef6c00; }
        .student-shiftee { background-color: #e8f5e8; color: #2e7d32; }
        .student-returnee { background-color: #f3e5f5; color: #7b1fa2; }
        .college-badge { padding: 0.3em 0.6em; border-radius: 8px; font-size: 0.75rem; background-color: #800000; color: white; }
        .table-hover tbody tr:hover { background-color: rgba(0,0,0,.025); }
        .file-badge { font-size: 0.7rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark shadow">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <img src="../assets/img/vid.jpg" width="40" height="40" class="me-2 rounded-circle"> E-RIS Admin
            </a>
            <div class="ms-auto text-white">
                Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong>
                <span class="badge bg-light text-maroon ms-2"><?php echo htmlspecialchars($_SESSION['admin_role']); ?></span>
                <?php if ($allowed_college !== 'all'): ?>
                    <span class="badge bg-info ms-2"><?php echo htmlspecialchars($allowed_college); ?> Admin</span>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline-light btn-sm ms-3">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Admin Menu -->
            <div class="col-md-3">
                <div class="card shadow-sm p-3">
                    <h5 class="text-maroon fw-bold mb-3">Admin Menu</h5>
                    <div class="list-group">
                        <a href="dashboard.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <?php if ($_SESSION['admin_role'] === 'head_admin'): ?>
                        <a href="manage_admins.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-plus me-2"></i>Manage Admins
                        </a>
                        <?php endif; ?>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="bi bi-book me-2"></i>Manage Courses (WIP)
                        </a>
                        <a href="view_submissions.php" class="list-group-item list-group-item-action active">
                            <i class="bi bi-folder me-2"></i>View Submissions
                        </a>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card shadow-sm p-3 mt-3">
                    <h6 class="text-maroon fw-bold mb-3">Submission Stats</h6>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total:</span>
                            <strong><?php echo $counts['total'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-warning">Pending:</span>
                            <strong><?php echo $counts['pending'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-success">Approved:</span>
                            <strong><?php echo $counts['approved'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-danger">Denied:</span>
                            <strong><?php echo $counts['denied'] ?? 0; ?></strong>
                        </div>
                    </div>
                    <?php if ($allowed_college !== 'all'): ?>
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Viewing submissions for <strong><?php echo $allowed_college; ?></strong> only
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="card shadow-sm p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="text-maroon fw-bold mb-0">
                            <i class="bi bi-folder me-2"></i>Student Submissions
                        </h3>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <form method="GET" class="row g-3">
                                <!-- College Filter (only show if head admin) -->
                                <?php if ($allowed_college === 'all'): ?>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">College</label>
                                    <select name="college" class="form-select" onchange="this.form.submit()">
                                        <option value="all" <?php echo ($_GET['college'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Colleges</option>
                                        <option value="BSBA" <?php echo ($_GET['college'] ?? '') === 'BSBA' ? 'selected' : ''; ?>>BSBA</option>
                                        <option value="BSHM" <?php echo ($_GET['college'] ?? '') === 'BSHM' ? 'selected' : ''; ?>>BSHM</option>
                                        <option value="BEED" <?php echo ($_GET['college'] ?? '') === 'BEED' ? 'selected' : ''; ?>>BEED</option>
                                        <option value="BSIT" <?php echo ($_GET['college'] ?? '') === 'BSIT' ? 'selected' : ''; ?>>BSIT</option>
                                    </select>
                                </div>
                                <?php else: ?>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">College</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($allowed_college); ?>" readonly style="background-color: #e9ecef;">
                                    <input type="hidden" name="college" value="<?php echo htmlspecialchars($allowed_college); ?>">
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Status</label>
                                    <select name="status" class="form-select" onchange="this.form.submit()">
                                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="denied" <?php echo $status_filter === 'denied' ? 'selected' : ''; ?>>Denied</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Student Type</label>
                                    <select name="student_type" class="form-select" onchange="this.form.submit()">
                                        <option value="all" <?php echo $student_type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                        <option value="Regular" <?php echo $student_type_filter === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                        <option value="Irregular" <?php echo $student_type_filter === 'Irregular' ? 'selected' : ''; ?>>Irregular</option>
                                        <option value="Shiftee" <?php echo $student_type_filter === 'Shiftee' ? 'selected' : ''; ?>>Shiftee</option>
                                        <option value="Returnee" <?php echo $student_type_filter === 'Returnee' ? 'selected' : ''; ?>>Returnee</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Search</label>
                                    <div class="input-group">
                                        <input type="text" name="search" class="form-control" placeholder="Search by name, student ID, or reference..." value="<?php echo htmlspecialchars($search_query); ?>">
                                        <button class="btn btn-outline-maroon" type="submit">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">&nbsp;</label>
                                    <a href="view_submissions.php" class="btn btn-outline-secondary w-100">Clear</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Submissions Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Reference ID</th>
                                    <th>College</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Files</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($submissions)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                            No submissions found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($submissions as $sub): 
                                        $student_files = $submission_files[$sub['student_db_id']] ?? [];
                                        $full_name = $sub['first_name'] . 
                                                   ($sub['middle_name'] ? ' ' . $sub['middle_name'] . ' ' : ' ') . 
                                                   $sub['last_name'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($full_name); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($sub['student_number']); ?></div>
                                            <span class="student-type-badge student-<?php echo strtolower($sub['student_status']); ?>">
                                                <?php echo htmlspecialchars($sub['student_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($sub['reference_id']); ?></code>
                                        </td>
                                        <td>
                                            <span class="college-badge"><?php echo htmlspecialchars($sub['major']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars(ucfirst($sub['type'])); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $sub['status']; ?>">
                                                <?php echo ucfirst($sub['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php if (!empty($student_files['eval_form'])): ?>
                                                    <span class="badge bg-primary file-badge" title="Evaluation Form">Eval</span>
                                                <?php endif; ?>
                                                <?php if (!empty($student_files['shiftee_form'])): ?>
                                                    <span class="badge bg-success file-badge" title="Shiftee Form">Shiftee</span>
                                                <?php endif; ?>
                                                <?php if (!empty($student_files['loi_form'])): ?>
                                                    <span class="badge bg-info file-badge" title="Letter of Intent">LOI</span>
                                                <?php endif; ?>
                                                <?php if (!empty($sub['advising_form'])): ?>
                                                    <span class="badge bg-warning file-badge" title="Advising Form">Advising</span>
                                                <?php endif; ?>
                                                <?php if (!empty($student_files['signature_form'])): ?>
                                                    <span class="badge bg-secondary file-badge" title="Signature">Signature</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($sub['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" 
                                                        class="btn btn-outline-primary view-submission-btn"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewSubmissionModal"
                                                        data-submission-id="<?php echo $sub['submission_id']; ?>"
                                                        data-student-name="<?php echo htmlspecialchars($full_name); ?>"
                                                        data-student-id="<?php echo htmlspecialchars($sub['student_number']); ?>"
                                                        data-reference-id="<?php echo htmlspecialchars($sub['reference_id']); ?>"
                                                        data-college="<?php echo htmlspecialchars($sub['major']); ?>"
                                                        data-year-level="<?php echo htmlspecialchars($sub['year_level']); ?>"
                                                        data-status="<?php echo $sub['status']; ?>"
                                                        data-feedback="<?php echo htmlspecialchars($sub['feedback'] ?? ''); ?>"
                                                        data-eval-form="<?php echo htmlspecialchars($student_files['eval_form'] ?? ''); ?>"
                                                        data-shiftee-form="<?php echo htmlspecialchars($student_files['shiftee_form'] ?? ''); ?>"
                                                        data-loi-form="<?php echo htmlspecialchars($student_files['loi_form'] ?? ''); ?>"
                                                        data-advising-form="<?php echo htmlspecialchars($sub['advising_form'] ?? ''); ?>"
                                                        data-signature-form="<?php echo htmlspecialchars($student_files['signature_form'] ?? ''); ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-outline-success update-status-btn"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#updateStatusModal"
                                                        data-submission-id="<?php echo $sub['submission_id']; ?>"
                                                        data-current-status="<?php echo $sub['status']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Submission Modal -->
    <div class="modal fade" id="viewSubmissionModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-folder2-open me-2"></i>Submission Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="submissionDetails">
                        <!-- Content will be loaded via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>Update Submission Status
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="submission_id" id="updateSubmissionId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select" id="statusSelect">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="denied">Denied</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Feedback (Optional)</label>
                            <textarea name="feedback" class="form-control" rows="3" placeholder="Add feedback for the student..." id="feedbackText"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View Submission Details
        document.querySelectorAll('.view-submission-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const submissionId = this.getAttribute('data-submission-id');
                const studentName = this.getAttribute('data-student-name');
                const studentId = this.getAttribute('data-student-id');
                const referenceId = this.getAttribute('data-reference-id');
                const college = this.getAttribute('data-college');
                const yearLevel = this.getAttribute('data-year-level');
                const status = this.getAttribute('data-status');
                const feedback = this.getAttribute('data-feedback');
                
                // File attributes
                const evalForm = this.getAttribute('data-eval-form');
                const shifteeForm = this.getAttribute('data-shiftee-form');
                const loiForm = this.getAttribute('data-loi-form');
                const advisingForm = this.getAttribute('data-advising-form');
                const signatureForm = this.getAttribute('data-signature-form');
                
                const statusBadge = status === 'pending' ? 'warning' : (status === 'approved' ? 'success' : 'danger');
                
                let filesHtml = '<div class="mb-3"><strong>Submitted Files:</strong><div class="mt-2">';
                
                if (evalForm) filesHtml += `<span class="badge bg-primary me-1">Evaluation Form</span>`;
                if (shifteeForm) filesHtml += `<span class="badge bg-success me-1">Shiftee Form</span>`;
                if (loiForm) filesHtml += `<span class="badge bg-info me-1">Letter of Intent</span>`;
                if (advisingForm) filesHtml += `<span class="badge bg-warning me-1">Advising Form</span>`;
                if (signatureForm) filesHtml += `<span class="badge bg-secondary me-1">Signature</span>`;
                
                filesHtml += '</div></div>';
                
                const detailsHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Student Name:</strong> ${studentName}</p>
                            <p><strong>Student ID:</strong> ${studentId}</p>
                            <p><strong>College:</strong> <span class="badge bg-maroon">${college}</span></p>
                            <p><strong>Year Level:</strong> ${yearLevel}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <span class="badge bg-${statusBadge}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></p>
                            <p><strong>Reference ID:</strong> <code>${referenceId}</code></p>
                            <p><strong>Submission ID:</strong> ${submissionId}</p>
                        </div>
                    </div>
                    ${filesHtml}
                    ${feedback ? `<div class="alert alert-info"><strong>Feedback:</strong><br>${feedback}</div>` : '<p class="text-muted">No feedback provided.</p>'}
                    <div class="text-center mt-3">
                        ${advisingForm ? `<a href="../${advisingForm}" target="_blank" class="btn btn-outline-primary me-2"><i class="bi bi-file-pdf me-1"></i>View Advising Form</a>` : ''}
                        ${evalForm ? `<a href="../uploads/evaluations/${evalForm}" target="_blank" class="btn btn-outline-secondary me-2"><i class="bi bi-file-text me-1"></i>View Evaluation</a>` : ''}
                        ${shifteeForm ? `<a href="../uploads/evaluations/${shifteeForm}" target="_blank" class="btn btn-outline-success me-2"><i class="bi bi-file-text me-1"></i>View Shiftee Form</a>` : ''}
                        ${loiForm ? `<a href="../uploads/evaluations/${loiForm}" target="_blank" class="btn btn-outline-info me-2"><i class="bi bi-file-text me-1"></i>View LOI</a>` : ''}
                    </div>
                `;
                
                document.getElementById('submissionDetails').innerHTML = detailsHtml;
            });
        });

        // Update Status Modal
        document.querySelectorAll('.update-status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const submissionId = this.getAttribute('data-submission-id');
                const currentStatus = this.getAttribute('data-current-status');
                
                document.getElementById('updateSubmissionId').value = submissionId;
                document.getElementById('statusSelect').value = currentStatus;
                document.getElementById('feedbackText').value = '';
            });
        });
    </script>
</body>
</html>