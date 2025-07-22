<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Only super admin can edit admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['edit_user_id'];
    $username = trim($_POST['edit_username']);
    $email = trim($_POST['edit_email']);
    $password = trim($_POST['edit_password']);

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $username, $email, $hashed_password, $user_id);
    } else {
        $sql = "UPDATE users SET username = ?, email = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $username, $email, $user_id);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Admin updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update admin.']);
    }
}
?>
