<?php
// get_cto_earnings.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// 1) Auth check: only HR can fetch CTO earnings
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

// 2) Validate input
$employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
if ($employee_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing employee_id'
    ]);
    exit();
}

// 3) Fetch CTO earnings records
$sql = "
    SELECT
        cto_id,
        days_earned,
        days_used,
        (days_earned - days_used) AS balance,
        earned_at,
        expires_at,
        source
    FROM cto_earnings
    WHERE employee_id = ?
    ORDER BY earned_at DESC
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error (prepare failed)'
    ]);
    exit();
}
$stmt->bind_param('i', $employee_id);
$stmt->execute();
$result = $stmt->get_result();

$ctos = [];
while ($row = $result->fetch_assoc()) {
    $ctos[] = [
        'cto_id'     => (int)$row['cto_id'],
        'days_earned'=> (float)$row['days_earned'],
        'days_used'  => (float)$row['days_used'],
        'balance'    => (float)$row['balance'],
        'earned_at'  => $row['earned_at'],
        'expires_at' => $row['expires_at'],
        'source'     => $row['source']
    ];
}
$stmt->close();

// 4) Return JSON
echo json_encode([
    'success' => true,
    'data'    => $ctos
]);
