<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

header('Content-Type: application/json');

// ── Auth guard ──
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}

// ── Method guard ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit();
}

$user_id = (int)($_POST['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid user ID.']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE users SET role='employee' WHERE user_id=? AND role='hr'");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success'=>true,'message'=>'HR downgraded to Employee.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'User not found or not an HR.']);
    }
    $stmt->close();
} catch (Throwable $th) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error.']);
    exit();
}
