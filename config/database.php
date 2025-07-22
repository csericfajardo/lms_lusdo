<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = ""; // update if your MySQL has password
$dbname = "depedlu_lms_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: set timezone for DB operations
date_default_timezone_set('Asia/Manila');
?>
