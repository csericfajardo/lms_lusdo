<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Check super admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['id'];

    $sql = "DELETE FROM users WHERE user_id = ? AND role = 'admin'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Admin deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete admin.']);
    }
}
?>
