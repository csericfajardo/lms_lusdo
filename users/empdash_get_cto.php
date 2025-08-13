<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once '../config/database.php';

// Require logged-in employee
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employee') {
    http_response_code(403);
    echo json_encode(['available_hours' => 0]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Get employee_id
$stmtEmp = $conn->prepare("SELECT employee_id FROM users WHERE user_id = ?");
$stmtEmp->bind_param("i", $user_id);
$stmtEmp->execute();
$resEmp = $stmtEmp->get_result()->fetch_assoc();
$stmtEmp->close();

if (!$resEmp) {
    echo json_encode(['available_hours' => 0]);
    exit();
}
$employee_id = (int) $resEmp['employee_id'];

// Sum available CTO hours
$sql = "SELECT SUM(days_earned - days_used) AS balance 
        FROM cto_earnings 
        WHERE employee_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

$balance = $res && $res['balance'] !== null ? (float) $res['balance'] : 0.0;

echo json_encode(['available_hours' => $balance]);
