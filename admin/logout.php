<?php
/** @var PDO $conn */
session_start();

include('../includes/db_connect.php');

if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];

    // Mark as inactive + clear session id
    $stmt = $conn->prepare("UPDATE admins SET status = 'inactive', session_id = NULL WHERE id = ?");
    $stmt->execute([$admin_id]);

    // Log logout
    $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, log_time)
                           VALUES (?, 'logout', 'Admin logged out', NOW())");
    $log->execute([$admin_id]);
}

// Destroy session
session_destroy();
header("Location: login.php");
exit();
?>
