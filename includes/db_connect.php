<?php
/**
 * Database connection
 * @var PDO $conn
 */

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "eris_db";

// Initialize connection variable
$conn = null;

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Ensure connection exists
if ($conn === null) {
    die("Failed to establish database connection");
}
?>