<?php
/** @var PDO $conn */
session_start();
include('../includes/db_connect.php');

// Redirect to login if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// ✅ Fetch the admin info fresh every request
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Check if admin still exists and is activated
if (!$admin || $admin['activation'] === 'deactivated') {
    // Force logout if deactivated
    session_unset();
    session_destroy();
    echo "<script>
        alert('Your account has been deactivated by the Head Admin.');
        window.location.href = 'login.php';
    </script>";
    exit();
}

// ✅ Optional: update login status (keep them marked as active)
if ($admin['status'] !== 'active') {
    $update = $conn->prepare("UPDATE admins SET status = 'active' WHERE id = ?");
    $update->execute([$admin_id]);
}
?>
