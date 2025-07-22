<?php
session_start();
require_once '../config/database.php';

// Check if super admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hr_id = $_POST['edit_hr_id'];
    $username = trim($_POST['edit_hr_username']);
    $email = trim($_POST['edit_hr_email']);
    $password = trim($_POST['edit_hr_password']);

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ? AND role = 'hr'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $username, $email, $hashed_password, $hr_id);
    } else {
        $sql = "UPDATE users SET username = ?, email = ? WHERE user_id = ? AND role = 'hr'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $username, $email, $hr_id);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'HR updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update HR.']);
    }
}
?>
