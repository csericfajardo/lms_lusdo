<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Allow only employees
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employee') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Get employee_id for logged-in user
$stmt = $conn->prepare("SELECT employee_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode([]);
    exit();
}

$employee_id = (int) $row['employee_id'];

// Fetch leave credits with formatted last update
$sqlCredits = "
    SELECT 
        lc.leave_type_id AS code,
        lt.name,
        (lc.total_credits - lc.used_credits) AS balance,
        DATE_FORMAT(lc.updated_at, '%b %d, %Y') AS last_update
    FROM leave_credits lc
    JOIN leave_types lt ON lc.leave_type_id = lt.leave_type_id
    WHERE lc.employee_id = ?
      AND lt.status = 'active'
    ORDER BY lt.name
";

$stmtCredits = $conn->prepare($sqlCredits);
$stmtCredits->bind_param("i", $employee_id);
$stmtCredits->execute();
$resultCredits = $stmtCredits->get_result();

$data = [];
while ($credit = $resultCredits->fetch_assoc()) {
    $data[] = [
        'code'        => $credit['code'],
        'name'        => $credit['name'],
        'balance'     => number_format((float)$credit['balance'], 2),
        'last_update' => $credit['last_update']
    ];
}
$stmtCredits->close();

header('Content-Type: application/json');
echo json_encode($data);
