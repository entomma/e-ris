<?php
session_start();
/** @var PDO $conn */
include('../includes/db_connect.php');

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {

            // ✅ Check activation column properly (case-insensitive)
            if (strtolower($admin['activation']) !== 'activated') {
                $error = "Your account is deactivated. Please contact the Head Admin.";
            } 
            // ✅ Check password if activated
            elseif (password_verify($password, $admin['password'])) {

                // ✅ Update status to active (currently logged in)
                $update = $conn->prepare("UPDATE admins SET status = 'active' WHERE id = ?");
                $update->execute([$admin['id']]);

                // ✅ Log admin login
                $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, log_time)
                                       VALUES (?, 'login', 'Admin logged in', NOW())");
                $log->execute([$admin['id']]);

                // ✅ Store session data
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }

        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-RIS | Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-control:focus { border-color: #800000 !important; box-shadow: 0 0 0 0.25rem rgba(128,0,0,0.25) !important; }
        .btn-primary { background-color: #800000 !important; border-color: #800000 !important; }
        .btn-primary:hover { background-color: #660000 !important; border-color: #660000 !important; }
        .text-maroon { color: #800000 !important; }
    </style>
</head>
<body class="bg-light">
    <div class="container d-flex flex-column justify-content-center align-items-center" style="min-height: 100vh;">
        
        <div class="text-center mb-4">
            <img src="../assets/img/vid.jpg" alt="E-RIS Logo" width="70" height="70" class="mb-2 rounded-circle shadow-sm">
            <h1 class="fw-bold text-maroon m-0">E-RIS Admin</h1>
            <p class="text-secondary m-0">Pampanga State University - Sto. Tomas Campus</p>
        </div>

        <div class="card shadow p-4" style="width: 400px;">
            <h3 class="text-center mb-3 fw-bold text-maroon">Admin Login</h3>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" placeholder="Enter your username" required>
                </div>

                <div class="mb-3 position-relative">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">
                            <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>

            <div class="text-center mt-3">
                <small><a href="../login.php" class="text-maroon">← Back to Student Login</a></small>
            </div>
        </div>
    </div>

    <script>
    const togglePassword = document.querySelector("#togglePassword");
    const passwordField = document.querySelector("#password");
    const icon = document.querySelector("#togglePasswordIcon");

    togglePassword.addEventListener("click", () => {
        const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
        passwordField.setAttribute("type", type);
        icon.classList.toggle("bi-eye");
        icon.classList.toggle("bi-eye-slash");
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
