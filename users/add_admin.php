<?php
session_start();
require_once '../config/database.php';

// Only allow super admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['new_username']);
    $email = trim($_POST['new_email']);
    $password = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $email, $password);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'New admin added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add admin.']);
    }
}
?>
