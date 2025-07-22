<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['new_username']);
    $email = trim($_POST['new_email']);
    $password = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'hr', 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $email, $password);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'HR added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add HR.']);
    }
}
?>
