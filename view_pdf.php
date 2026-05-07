<?php

session_start();

include('includes/db_connect.php');

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$submission_id = intval($_GET['id']);
$student_db_id = $_SESSION['student_id'];

// Verify ownership and get PDF path
$stmt = $conn->prepare("
    SELECT s.advising_form, s.reference_id, stu.student_id as student_number
    FROM submissions s 
    JOIN students stu ON s.student_id = stu.id 
    WHERE s.submission_id = ? AND s.student_id = ?
");
$stmt->execute([$submission_id, $student_db_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    header('Content-Type: text/html');
    echo "<h2>Error: Submission not found or access denied</h2>";
    exit();
}

if (empty($submission['advising_form'])) {
    header('Content-Type: text/html');
    echo "<h2>Error: No PDF path in database</h2>";
    exit();
}

// FIXED: Use the correct base path that includes E-RIS folder
$base_dir = $_SERVER['DOCUMENT_ROOT'] . '/E-RIS/';
$pdf_path = $base_dir . $submission['advising_form'];

// DEBUG
error_log("Base Dir: " . $base_dir);
error_log("PDF Path: " . $pdf_path);
error_log("File Exists: " . (file_exists($pdf_path) ? 'YES' : 'NO'));

if (!file_exists($pdf_path)) {
    // Try a few alternative paths
    $alt_paths = [
        '../' . $submission['advising_form'], // Relative from student folder
        $_SERVER['DOCUMENT_ROOT'] . '/E-RIS/' . $submission['advising_form'], // Absolute
        'C:/xampp/htdocs/E-RIS/' . $submission['advising_form'] // Hardcoded absolute
    ];
    
    $found = false;
    foreach ($alt_paths as $alt_path) {
        if (file_exists($alt_path)) {
            $pdf_path = $alt_path;
            $found = true;
            error_log("Found PDF at: " . $alt_path);
            break;
        }
    }
    
    if (!$found) {
        header('Content-Type: text/html');
        echo "<h2>PDF File Missing</h2>";
        echo "<p>We tried these paths:</p>";
        echo "<ul>";
        foreach ($alt_paths as $path) {
            echo "<li>" . htmlspecialchars($path) . " - " . (file_exists($path) ? "EXISTS" : "MISSING") . "</li>";
        }
        echo "</ul>";
        exit();
    }
}

// Handle download
if (isset($_GET['download']) && $_GET['download'] == '1') {
    $filename = 'advising_form_' . $submission['student_number'] . '_' . $submission['reference_id'] . '.pdf';
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . filesize($pdf_path));
    readfile($pdf_path);
    exit();
}

// Handle inline viewing
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="advising_form.pdf"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Content-Length: ' . filesize($pdf_path));
readfile($pdf_path);
exit();
?>