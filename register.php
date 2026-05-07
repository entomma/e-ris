<?php include('includes/db_connect.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-RIS | Register</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
    .form-control:focus, .form-select:focus {
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
    a { color: #800000; }
    a:hover { color: #660000; text-decoration: underline; }
    .text-maroon { color: #800000 !important; }
    .password-hint { font-size: 0.85rem; color: #888; margin-top: 4px; }
    .invalid-feedback { display: block; }
</style>
</head>
<body class="bg-light">

<div class="container d-flex flex-column justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="text-center mb-4">
        <img src="assets/img/vid.jpg" alt="E-RIS Logo" width="70" height="70" class="mb-2 rounded-circle shadow-sm">
        <h1 class="fw-bold text-maroon m-0">E-RIS</h1>
        <p class="text-secondary m-0">Pampanga State University - Sto. Tomas Campus</p>
    </div>

    <div class="card shadow p-4" style="width: 450px;">
        <h3 class="text-center mb-3 fw-bold text-maroon">Student Registration</h3>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = trim($_POST['student_id']);
    $first_name = ucwords(strtolower(trim($_POST['first_name'])));
    $middle_name = trim($_POST['middle_name']) ? ucwords(strtolower(trim($_POST['middle_name']))) : null;
    $last_name = ucwords(strtolower(trim($_POST['last_name'])));
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Safe handling for undefined keys
    $year_level = isset($_POST['year_level']) ? trim($_POST['year_level']) : '';
    $major = isset($_POST['major']) ? trim($_POST['major']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $mobile_number = trim($_POST['mobile_number']);
    $secondary_mobile_number = (isset($_POST['secondary_mobile_number']) && trim($_POST['secondary_mobile_number']) !== '') ? trim($_POST['secondary_mobile_number']) : null;

    $errors = [];

    // Required fields check (middle and secondary are optional)
    if (empty($student_id) || empty($first_name) || empty($last_name) || empty($email) ||
        empty($password) || empty($confirm_password) || empty($year_level) ||
        empty($major) || empty($mobile_number) || empty($status)) {
        $errors[] = "Please fill in all required fields.";
    }

    // Password validation
    if (!empty($password)) {
        if (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) ||
            !preg_match("/[0-9]/", $password) || !preg_match("/[\W_]/", $password)) {
            $errors[] = "Password must be at least 8 characters and include an uppercase letter, a number, and a special character.";
        }
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Email and student_id uniqueness
    if (empty($errors)) {
        $check = $conn->prepare("SELECT * FROM students WHERE email = ? OR student_id = ?");
        $check->execute([$email, $student_id]);

        if ($check->rowCount() > 0) {
            echo '<div class="alert alert-danger">Email or Student ID already registered.</div>';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO students 
                (student_id, first_name, middle_name, last_name, email, password, year_level, major, mobile_number, secondary_mobile_number, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student_id, $first_name, $middle_name, $last_name, $email, $hashed_password, $year_level, $major, $mobile_number, $secondary_mobile_number, $status]);

            $lastStudentId = $conn->lastInsertId();

            // Insert blank row into files table
            $stmtFiles = $conn->prepare("
                INSERT INTO files (student_id, eval_form, shiftee_form, loi_form, evaluation_uploaded, reference_id, created_at)
                VALUES (?, NULL, NULL, NULL, 0, NULL, NOW())
            ");
            $stmtFiles->execute([$lastStudentId]);

            echo '<div class="alert alert-success text-center">Registration successful! You can now <a href="login.php">login</a>.</div>';
        }
    } else {
        foreach ($errors as $error) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
        }
    }
}
?>

<form method="POST" novalidate>
    <div class="mb-3">
        <label class="form-label">Student ID</label>
        <input type="text" class="form-control" name="student_id" id="student_id" pattern="[0-9]{10}" maxlength="10" placeholder="e.g. 2025123456 (10 digits only)" required>
        <div class="invalid-feedback" id="studentIdError"></div>
    </div>

    <div class="mb-3 position-relative">
        <label class="form-label">Password</label>
        <div class="input-group">
            <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required>
            <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">
                <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
            </button>
        </div>
        <div class="password-hint" id="passwordHint">Minimum 8 characters, must include uppercase, number, and special character.</div>
    </div>

    <div class="mb-3 position-relative">
        <label class="form-label">Confirm Password</label>
        <div class="input-group">
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
            <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPassword" tabindex="-1">
                <i class="bi bi-eye-slash" id="toggleConfirmPasswordIcon"></i>
            </button>
        </div>
        <div class="invalid-feedback" id="passwordError"></div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" class="form-control" name="first_name" placeholder="e.g. John" required>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Middle Name</label>
            <input type="text" class="form-control" name="middle_name" placeholder="(Optional)">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" class="form-control" name="last_name" placeholder="e.g. Cena" required>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" name="email" id="email" placeholder="e.g. johncena@gmail.com" required>
        <div class="invalid-feedback" id="emailError"></div>
    </div>

    <div class="mb-3">
        <label class="form-label d-block">Student Status <span class="text-danger">*</span></label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="statusIrregular" value="Irregular" required>
            <label class="form-check-label" for="statusIrregular">Irregular</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="statusShiftee" value="Shiftee" required>
            <label class="form-check-label" for="statusShiftee">Shiftee</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="statusReturnee" value="Returnee" required>
            <label class="form-check-label" for="statusReturnee">Returnee</label>
        </div>
        <div class="invalid-feedback d-block" id="statusError"></div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Year Level</label>
            <select class="form-select" name="year_level" required>
                <option value="" disabled selected>Select Year</option>
                <option value="1st Year">1st Year</option>
                <option value="2nd Year">2nd Year</option>
                <option value="3rd Year">3rd Year</option>
                <option value="4th Year">4th Year</option>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Major</label>
            <select class="form-select" name="major" required>
                <option value="" disabled selected>Select Major</option>
                <option value="Bachelor of Science in Information Technology">Bachelor of Science in Information Technology</option>
                <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                <option value="Bachelor of Elementary Education">Bachelor of Elementary Education</option>
                <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
            </select>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Mobile Number</label>
        <input type="text" class="form-control" name="mobile_number" id="mobile_number" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" required>
        <div class="invalid-feedback" id="mobileError"></div>
    </div>

    <div class="mb-3">
        <label class="form-label">Secondary Mobile Number</label>
        <input type="text" class="form-control" name="secondary_mobile_number" id="secondary_mobile_number" pattern="09[0-9]{9}" maxlength="11" placeholder="(Optional)">
        <div class="invalid-feedback" id="secondaryMobileError"></div>
    </div>

    <button type="submit" class="btn btn-primary w-100">Register</button>
</form>

<div class="text-center mt-3">
    <small>Already registered? <a href="login.php">Login here</a></small>
</div>
</div>
</div>

<script>
// Password validation JS
const passwordInput = document.getElementById("password");
const confirmInput = document.getElementById("confirm_password");
const errorDiv = document.getElementById("passwordError");

function validatePassword() {
    const pass = passwordInput.value;
    const confirm = confirmInput.value;
    const pattern = /^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

    if (pass && !pattern.test(pass)) {
        errorDiv.textContent = "Password must contain at least 8 characters, one uppercase letter, one number, and one special character.";
        confirmInput.classList.add("is-invalid");
    } else if (confirm && pass !== confirm) {
        errorDiv.textContent = "Passwords do not match.";
        confirmInput.classList.add("is-invalid");
    } else {
        errorDiv.textContent = "";
        confirmInput.classList.remove("is-invalid");
    }
}

passwordInput.addEventListener("input", validatePassword);
confirmInput.addEventListener("input", validatePassword);

// Show/hide password toggle
const togglePassword = document.querySelector("#togglePassword");
const toggleConfirm = document.querySelector("#toggleConfirmPassword");
const passwordField = document.querySelector("#password");
const confirmField = document.querySelector("#confirm_password");
const icon1 = document.querySelector("#togglePasswordIcon");
const icon2 = document.querySelector("#toggleConfirmPasswordIcon");

togglePassword.addEventListener("click", () => {
    const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
    passwordField.setAttribute("type", type);
    icon1.classList.toggle("bi-eye");
    icon1.classList.toggle("bi-eye-slash");
});

toggleConfirm.addEventListener("click", () => {
    const type = confirmField.getAttribute("type") === "password" ? "text" : "password";
    confirmField.setAttribute("type", type);
    icon2.classList.toggle("bi-eye");
    icon2.classList.toggle("bi-eye-slash");
});

// Student ID validation
const studentIdInput = document.getElementById("student_id");
const studentIdError = document.getElementById("studentIdError");
studentIdInput.addEventListener("input", () => {
    studentIdInput.value = studentIdInput.value.replace(/[^0-9]/g, "");
    if (studentIdInput.value.length > 0 && studentIdInput.value.length !== 10) {
        studentIdError.textContent = "Student ID must be exactly 10 digits.";
        studentIdInput.classList.add("is-invalid");
    } else {
        studentIdError.textContent = "";
        studentIdInput.classList.remove("is-invalid");
    }
});

// Email validation
const emailInput = document.getElementById("email");
const emailError = document.getElementById("emailError");
emailInput.addEventListener("input", () => {
    const allowedDomains = ["@gmail.com", "@yahoo.com", "@pampangastateu.edu.ph"];
    const emailValue = emailInput.value.toLowerCase();
    const isValid = allowedDomains.some(domain => emailValue.endsWith(domain));
    if (emailValue && !isValid) {
        emailError.textContent = "Email must end with @gmail.com, @yahoo.com, or @pampangastateu.edu.ph.";
        emailInput.classList.add("is-invalid");
    } else {
        emailError.textContent = "";
        emailInput.classList.remove("is-invalid");
    }
});

// Mobile validation
const mobileInput = document.getElementById("mobile_number");
const secondaryInput = document.getElementById("secondary_mobile_number");
const mobileError = document.getElementById("mobileError");
const secondaryError = document.getElementById("secondaryMobileError");

function validateMobile(input, errorDiv) {
    input.value = input.value.replace(/[^0-9]/g, "");
    if (input.value && !/^09\d{9}$/.test(input.value)) {
        errorDiv.textContent = "Must start with 09 and be exactly 11 digits.";
        input.classList.add("is-invalid");
    } else {
        errorDiv.textContent = "";
        input.classList.remove("is-invalid");
    }
}

mobileInput.addEventListener("input", () => validateMobile(mobileInput, mobileError));
secondaryInput.addEventListener("input", () => validateMobile(secondaryInput, secondaryError));

// Validate that one status is selected
const statusRadios = document.querySelectorAll('input[name="status"]');
const statusError = document.getElementById('statusError');

document.querySelector('form').addEventListener('submit', (e) => {
    if (![...statusRadios].some(r => r.checked)) {
        e.preventDefault();
        statusError.textContent = "Please select your student status.";
    } else {
        statusError.textContent = "";
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>