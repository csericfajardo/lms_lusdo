<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// ✅ Only allow logged-in employees
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employee') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get employee_id from session
$employee_id = $_SESSION['employee_id'] ?? null;
if (!$employee_id) {
    echo json_encode(['success' => false, 'message' => 'No employee record found for this account.']);
    exit();
}

$sql = "
    SELECT 
        lc.leave_type_id,
        lt.name AS name,
        CASE 
            WHEN lt.name LIKE '%Vacation%' THEN 'VL'
            WHEN lt.name LIKE '%Sick%' THEN 'SL'
            WHEN lt.name LIKE '%Compensatory%' THEN 'CTO'
            ELSE CONCAT('LT', lt.leave_type_id)
        END AS code,
        lc.balance_credits AS balance,
        lc.updated_at AS last_update
    FROM leave_credits lc
    INNER JOIN leave_types lt ON lc.leave_type_id = lt.leave_type_id
    WHERE lc.employee_id = ?
    ORDER BY lt.name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

$credits = [];
while ($row = $result->fetch_assoc()) {
    $credits[] = [
        'leave_type_id' => (int)$row['leave_type_id'],
        'code'          => $row['code'],
        'name'          => $row['name'],
        'balance'       => (float)$row['balance'],
        'last_update'   => $row['last_update']
            ? date('M d, Y', strtotime($row['last_update']))
            : null
    ];
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($credits);
