<?php
/** @var PDO $conn */
include('../includes/db_connect.php');
include('../includes/auth_admin.php');

// Handle semester change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_semester') {
    $new_semester = intval($_POST['semester']);
    
    if ($new_semester < 1 || $new_semester > 2) {
        $_SESSION['error'] = 'Invalid semester selected';
    } else {
        $stmt = $conn->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES ('current_semester', ?)");
        $stmt->execute([$new_semester]);
        $_SESSION['success'] = "Global semester updated to Semester $new_semester for ALL students!";
    }
    header("Location: dashboard.php");
    exit();
}

// Get current semester
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'current_semester'");
$stmt->execute();
$current_semester = $stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-RIS | Admin Dashboard</title>
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
        .semester-card {
            border-left: 4px solid #800000;
        }
        .semester-option {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .semester-option:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }
        .semester-option.active {
            border-color: #800000;
            background-color: #fff5f5;
        }
        .current-semester-badge {
            background-color: #800000;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .btn-maroon {
            background-color: #800000;
            color: white;
        }
        .btn-maroon:hover {
            background-color: #600000;
            color: white;
        }
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
                        <a href="dashboard.php" class="list-group-item list-group-item-action active">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <?php if ($_SESSION['admin_role'] === 'head_admin'): ?>
                        <a href="manage_admins.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-plus me-2"></i>Manage Admins
                        </a>
                        <?php endif; ?>
                        
                        <a href="import_schedule.php" class="list-group-item list-group-item-action">
    <i class="bi bi-upload me-2"></i>Import Schedule
</a>
                            <a href="delete_schedule.php" class="list-group-item list-group-item-action"><i class="bi bi-trash me-2"></i>Delete Schedule</a>
                        <a href="view_submissions.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-folder me-2"></i>View Submissions
                        </a>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="col-md-9">
                <div class="card shadow-sm p-4 mb-4">
                    <h3 class="text-maroon fw-bold mb-4">Admin Dashboard</h3>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Students</h5>
                                    <h2 class="card-text">
                                        <?php
                                        $stmt = $conn->query("SELECT COUNT(*) FROM students");
                                        echo $stmt->fetchColumn();
                                        ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Courses</h5>
                                    <h2 class="card-text">
                                        <?php
                                        $stmt = $conn->query("SELECT COUNT(*) FROM courses");
                                        echo $stmt->fetchColumn();
                                        ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Pending Submissions</h5>
                                    <h2 class="card-text">
                                        <?php
                                        $stmt = $conn->query("SELECT COUNT(*) FROM submissions WHERE status = 'pending'");
                                        echo $stmt->fetchColumn();
                                        ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Admins</h5>
                                    <h2 class="card-text">
                                        <?php
                                        $stmt = $conn->query("SELECT COUNT(*) FROM admins WHERE status = 'active'");
                                        echo $stmt->fetchColumn();
                                        ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-maroon fw-bold mb-3">Quick Actions</h5>
                            <div class="d-grid gap-2 d-md-flex">
                                <a href="manage_students.php" class="btn btn-outline-maroon me-2">
                                    <i class="bi bi-person-plus me-2"></i>Add Student
                                </a>
                                <a href="manage_courses.php" class="btn btn-outline-maroon me-2">
                                    <i class="bi bi-book me-2"></i>Add Course
                                </a>
                                <?php if ($_SESSION['admin_role'] === 'head_admin'): ?>
                                <a href="manage_admins.php" class="btn btn-outline-maroon">
                                    <i class="bi bi-person-plus me-2"></i>Add Admin
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Semester Control Card -->
                <div class="card shadow-sm p-4 semester-card">
                    <h5 class="text-maroon fw-bold mb-3">
                        <i class="bi bi-calendar-event me-2"></i>Global Semester Control
                    </h5>
                    
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <p class="text-muted mb-3">
                                Set the current semester for ALL students. This controls which courses are available for enrollment.
                            </p>
                            <div class="d-flex gap-3">
                                <!-- Semester 1 Option -->
                                <div class="semester-option text-center p-3 rounded <?php echo $current_semester == '1' ? 'active' : ''; ?>" 
                                    style="cursor: pointer; min-width: 120px;" 
                                    onclick="selectSemester(1)">
                                    <div class="h4 mb-2">1</div>
                                    <div class="fw-bold">1st Semester</div>
                                    <?php if ($current_semester == '1'): ?>
                                        <div class="current-semester-badge mt-2">Current</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Semester 2 Option -->
                                <div class="semester-option text-center p-3 rounded <?php echo $current_semester == '2' ? 'active' : ''; ?>" 
                                    style="cursor: pointer; min-width: 120px;" 
                                    onclick="selectSemester(2)">
                                    <div class="h4 mb-2">2</div>
                                    <div class="fw-bold">2nd Semester</div>
                                    <?php if ($current_semester == '2'): ?>
                                        <div class="current-semester-badge mt-2">Current</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-maroon btn-lg" onclick="confirmSemesterChange()" disabled id="changeBtn">
                                <i class="bi bi-arrow-repeat me-2"></i>Change Semester
                            </button>
                        </div>
                    </div>

                    <!-- Current Status -->
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="text-maroon mb-2"><i class="bi bi-info-circle me-2"></i>Current Status</h6>
                        <p class="mb-1"><strong>Active Semester:</strong> Semester <?php echo $current_semester; ?></p>
                        <p class="mb-0 text-muted small">
                            All students can only enroll in courses from Semester <?php echo $current_semester; ?>.
                            Courses from other semesters will show as "Not Available".
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-maroon">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirm Semester Change
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to change the global semester from <strong>Semester <?php echo $current_semester; ?></strong> to <strong id="newSemesterText">Semester X</strong>.</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>This will affect ALL students:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Only courses from the new semester will be available for enrollment</li>
                            <li>Courses from other semesters will become unavailable</li>
                            <li>This change is system-wide and immediate</li>
                        </ul>
                    </div>
                    <p>Are you sure you want to proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="semesterForm">
                        <input type="hidden" name="action" value="change_semester">
                        <input type="hidden" name="semester" id="selectedSemester">
                        <button type="submit" class="btn btn-maroon">Yes, Change Semester</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedSemester = null;
        const currentSemester = <?php echo $current_semester; ?>;

        function selectSemester(semester) {
            // Don't allow selecting the current semester
            if (semester === currentSemester) {
                return;
            }

            // Update UI
            document.querySelectorAll('.semester-option').forEach(option => {
                option.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            selectedSemester = semester;
            document.getElementById('changeBtn').disabled = false;
        }

        function confirmSemesterChange() {
            if (!selectedSemester) return;

            // Update modal content
            document.getElementById('newSemesterText').textContent = 'Semester ' + selectedSemester;
            document.getElementById('selectedSemester').value = selectedSemester;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            modal.show();
        }

        // Initialize button state
        document.getElementById('changeBtn').disabled = true;
    </script>
</body>
</html>