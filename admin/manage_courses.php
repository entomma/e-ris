<?php
/** @var PDO $conn */
include('../includes/db_connect.php');
include('../includes/auth_admin.php');

// Handle Add Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $course_code = trim($_POST['course_code']);
    $course_title = trim($_POST['course_title']);
    $units = floatval($_POST['units']);
    $year_level = intval($_POST['year_level']);
    $semester = intval($_POST['semester']);
    $major = trim($_POST['major']);
    
    if (empty($course_code) || empty($course_title)) {
        $_SESSION['error'] = "Course code and title are required!";
    } else {
        // Check if course code already exists
        $check = $conn->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ?");
        $check->execute([$course_code]);
        
        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "Course code already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO courses (course_code, course_title, units, year_level, semester, major, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$course_code, $course_title, $units, $year_level, $semester, $major]);
            $_SESSION['success'] = "Course added successfully!";
        }
    }
    header("Location: manage_courses.php");
    exit();
}

// Handle Edit Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_course'])) {
    $course_id = intval($_POST['course_id']);
    $course_code = trim($_POST['course_code']);
    $course_title = trim($_POST['course_title']);
    $units = floatval($_POST['units']);
    $year_level = intval($_POST['year_level']);
    $semester = intval($_POST['semester']);
    $major = trim($_POST['major']);
    
    if (empty($course_code) || empty($course_title)) {
        $_SESSION['error'] = "Course code and title are required!";
    } else {
        // Check if course code exists for another course
        $check = $conn->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ? AND course_id != ?");
        $check->execute([$course_code, $course_id]);
        
        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "Course code already exists!";
        } else {
            $stmt = $conn->prepare("UPDATE courses SET course_code = ?, course_title = ?, units = ?, year_level = ?, semester = ?, major = ? WHERE course_id = ?");
            $stmt->execute([$course_code, $course_title, $units, $year_level, $semester, $major, $course_id]);
            $_SESSION['success'] = "Course updated successfully!";
        }
    }
    header("Location: manage_courses.php");
    exit();
}

// Handle Delete Course
if (isset($_GET['delete'])) {
    $course_id = intval($_GET['delete']);
    
    // Check if course has any enrollments
    $check = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
    $check->execute([$course_id]);
    
    if ($check->fetchColumn() > 0) {
        $_SESSION['error'] = "Cannot delete course with existing enrollments!";
    } else {
        $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $_SESSION['success'] = "Course deleted successfully!";
    }
    header("Location: manage_courses.php");
    exit();
}

// Get filter parameters
$semester_filter = $_GET['semester'] ?? 'all';
$year_filter = $_GET['year_level'] ?? 'all';
$major_filter = $_GET['major'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($semester_filter !== 'all') {
    $where_conditions[] = "semester = ?";
    $params[] = $semester_filter;
}

if ($year_filter !== 'all') {
    $where_conditions[] = "year_level = ?";
    $params[] = $year_filter;
}

if ($major_filter !== 'all') {
    $where_conditions[] = "major = ?";
    $params[] = $major_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(course_title LIKE ? OR course_code LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get courses
$query = "SELECT * FROM courses $where_clause ORDER BY year_level, semester, course_code";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct majors for filter
$majors_stmt = $conn->query("SELECT DISTINCT major FROM courses ORDER BY major");
$majors = $majors_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-RIS | Manage Courses</title>
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
        .btn-maroon {
            background-color: #800000;
            color: white;
        }
        .btn-maroon:hover {
            background-color: #600000;
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(128, 0, 0, 0.05);
        }
        .course-badge {
            padding: 0.3em 0.6em;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .semester-badge {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        .year-badge {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        .major-badge {
            background-color: #800000;
            color: white;
        }
        .modal-header.bg-maroon {
            background-color: #800000;
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
                        <a href="dashboard.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <?php if ($_SESSION['admin_role'] === 'head_admin'): ?>
                        <a href="manage_admins.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-plus me-2"></i>Manage Admins
                        </a>
                        <?php endif; ?>
                        
                        <a href="manage_courses.php" class="list-group-item list-group-item-action active">
                            <i class="bi bi-book me-2"></i>Manage Courses
                        </a>
                        <a href="view_submissions.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-folder me-2"></i>View Submissions
                        </a>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card shadow-sm p-3 mt-3">
                    <h6 class="text-maroon fw-bold mb-3">Course Statistics</h6>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Courses:</span>
                            <strong><?php echo count($courses); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>1st Semester:</span>
                            <strong><?php 
                                $count = $conn->prepare("SELECT COUNT(*) FROM courses WHERE semester = 1");
                                $count->execute();
                                echo $count->fetchColumn();
                            ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>2nd Semester:</span>
                            <strong><?php 
                                $count = $conn->prepare("SELECT COUNT(*) FROM courses WHERE semester = 2");
                                $count->execute();
                                echo $count->fetchColumn();
                            ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="card shadow-sm p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="text-maroon fw-bold mb-0">
                            <i class="bi bi-book me-2"></i>Manage Courses
                        </h3>
                        <button type="button" class="btn btn-maroon" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                            <i class="bi bi-plus-circle me-2"></i>Add New Course
                        </button>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Major/College</label>
                                    <select name="major" class="form-select" onchange="this.form.submit()">
                                        <option value="all" <?php echo $major_filter === 'all' ? 'selected' : ''; ?>>All Majors</option>
                                        <?php foreach ($majors as $major): ?>
                                            <option value="<?php echo htmlspecialchars($major); ?>" <?php echo $major_filter === $major ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($major); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Semester</label>
                                    <select name="semester" class="form-select" onchange="this.form.submit()">
                                        <option value="all" <?php echo $semester_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="1" <?php echo $semester_filter === '1' ? 'selected' : ''; ?>>1st Semester</option>
                                        <option value="2" <?php echo $semester_filter === '2' ? 'selected' : ''; ?>>2nd Semester</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Year Level</label>
                                    <select name="year_level" class="form-select" onchange="this.form.submit()">
                                        <option value="all" <?php echo $year_filter === 'all' ? 'selected' : ''; ?>>All Years</option>
                                        <option value="1" <?php echo $year_filter === '1' ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2" <?php echo $year_filter === '2' ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3" <?php echo $year_filter === '3' ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4" <?php echo $year_filter === '4' ? 'selected' : ''; ?>>4th Year</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Search</label>
                                    <div class="input-group">
                                        <input type="text" name="search" class="form-control" placeholder="Search by code or title..." value="<?php echo htmlspecialchars($search_query); ?>">
                                        <button class="btn btn-outline-maroon" type="submit">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">&nbsp;</label>
                                    <a href="manage_courses.php" class="btn btn-outline-secondary w-100">Clear</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Courses Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Course Title</th>
                                    <th>Units</th>
                                    <th>Major/College</th>
                                    <th>Semester</th>
                                    <th>Year Level</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($courses)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                            No courses found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td>
                                            <code class="fw-bold"><?php echo htmlspecialchars($course['course_code']); ?></code>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($course['course_title']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $course['units']; ?> Units</span>
                                        </td>
                                        <td>
                                            <span class="course-badge major-badge"><?php echo htmlspecialchars($course['major']); ?></span>
                                        </td>
                                        <td>
                                            <span class="course-badge semester-badge">
                                                <?php echo $course['semester'] == 1 ? '1st Semester' : '2nd Semester'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="course-badge year-badge">
                                                <?php echo $course['year_level']; ?> Year
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" 
                                                        class="btn btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editCourseModal" 
                                                        onclick='editCourse(<?php echo json_encode($course); ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="?delete=<?php echo $course['course_id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this course? This action cannot be undone.');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
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

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Add New Course
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="add_course" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Course Code *</label>
                            <input type="text" name="course_code" class="form-control" placeholder="e.g., CC 113(A)" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Course Title *</label>
                            <input type="text" name="course_title" class="form-control" placeholder="e.g., Introduction to Computing" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Units</label>
                                <input type="number" name="units" class="form-control" step="0.5" min="1" max="6" value="3" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Major/College</label>
                                <select name="major" class="form-select" required>
                                    <option value="">Select Major</option>
                                    <option value="BSIT">BSIT - Information Technology</option>
                                    <option value="BSBA">BSBA - Business Administration</option>
                                    <option value="BSHM">BSHM - Hospitality Management</option>
                                    <option value="BEED">BEED - Elementary Education</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Semester</label>
                                <select name="semester" class="form-select" required>
                                    <option value="1">1st Semester</option>
                                    <option value="2">2nd Semester</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Year Level</label>
                                <select name="year_level" class="form-select" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-maroon">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>Edit Course
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_course" value="1">
                        <input type="hidden" name="course_id" id="edit_course_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Course Code *</label>
                            <input type="text" name="course_code" id="edit_course_code" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Course Title *</label>
                            <input type="text" name="course_title" id="edit_course_title" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Units</label>
                                <input type="number" name="units" id="edit_units" class="form-control" step="0.5" min="1" max="6" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Major/College</label>
                                <select name="major" id="edit_major" class="form-select" required>
                                    <option value="BSIT">BSIT - Information Technology</option>
                                    <option value="BSBA">BSBA - Business Administration</option>
                                    <option value="BSHM">BSHM - Hospitality Management</option>
                                    <option value="BEED">BEED - Elementary Education</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Semester</label>
                                <select name="semester" id="edit_semester" class="form-select" required>
                                    <option value="1">1st Semester</option>
                                    <option value="2">2nd Semester</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Year Level</label>
                                <select name="year_level" id="edit_year_level" class="form-select" required>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-maroon">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCourse(course) {
            document.getElementById('edit_course_id').value = course.course_id;
            document.getElementById('edit_course_code').value = course.course_code;
            document.getElementById('edit_course_title').value = course.course_title;
            document.getElementById('edit_units').value = course.units;
            document.getElementById('edit_major').value = course.major;
            document.getElementById('edit_semester').value = course.semester;
            document.getElementById('edit_year_level').value = course.year_level;
        }
    </script>
</body>
</html>