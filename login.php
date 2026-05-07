<?php include('includes/db_connect.php'); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-RIS | Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }

    .form-control:focus {
      border-color: #800000 !important;
      box-shadow: 0 0 0 0.25rem rgba(128, 0, 0, 0.25) !important;
    }

    .btn-primary {
      background-color: #800000 !important;
      border-color: #800000 !important;
    }

    .btn-primary:hover {
      background-color: #660000 !important;
      border-color: #660000 !important;
    }

    a {
      color: #800000;
    }

    a:hover {
      color: #660000;
      text-decoration: underline;
    }

    .text-maroon {
      color: #800000 !important;
    }
  </style>
</head>

<body class="bg-light">
  <div class="container d-flex flex-column justify-content-center align-items-center" style="min-height: 100vh;">

    <!-- 🌸 Header -->
    <div class="text-center mb-4">
      <img src="assets/img/vid.jpg" alt="E-RIS Logo" width="70" height="70" class="mb-2 rounded-circle shadow-sm">
      <h1 class="fw-bold text-maroon m-0">E-RIS</h1>
      <p class="text-secondary m-0">Pampanga State University - Sto. Tomas Campus</p>
    </div>

    <!-- 🌸 Login Card -->
    <div class="card shadow p-4" style="width: 400px;">
      <h3 class="text-center mb-3 fw-bold text-maroon">Student Login</h3>

      <?php
      session_start();

      if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $student_number = trim($_POST['student_id']); // This is the student number (5555555555)
        $password = trim($_POST['password']);

        if (empty($student_number) || empty($password)) {
          echo '<div class="alert alert-danger">Please fill in both fields.</div>';
        } else {
          $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
          $stmt->execute([$student_number]);
          $user = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($user && password_verify($password, $user['password'])) {
            // Store BOTH the primary key ID and student number in session
            $_SESSION['student_id'] = $user['id']; // Primary key (24) - for database queries
            $_SESSION['student_number'] = $user['student_id']; // Student number (5555555555) - for display
            $_SESSION['student_name'] = $user['first_name'] . ' ' . $user['last_name'];

            header("Location: dashboard.php");
            exit();
          } else {
            echo '<div class="alert alert-danger text-center">Invalid Student ID or Password.</div>';
          }
        }
      }
      ?>

      <form method="POST" novalidate>
        <div class="mb-3">
          <label class="form-label">Student ID</label>
          <input type="text" class="form-control" name="student_id" placeholder="Enter your Student ID" required value="">
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
        <small>Don't have an account? <a href="register.php">Register here</a></small>
      </div>
    </div>
  </div>

  <script>
    // 👁️ Toggle password visibility
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