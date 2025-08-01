<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// 1) Access control: only HR users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

// 2) Validate input
$empId = intval($_GET['employee_id'] ?? 0);
if ($empId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid employee_id']);
    exit();
}

// 3) Fetch non-CTO leave_type_ids already assigned
$stmt = $conn->prepare("
    SELECT leave_type_id
      FROM leave_credits
     WHERE employee_id = ?
       AND leave_type_id <> 12
");
$stmt->bind_param('i', $empId);
$stmt->execute();
$res = $stmt->get_result();
$assigned = array_map(fn($r) => (int)$r['leave_type_id'], $res->fetch_all(MYSQLI_ASSOC));
$stmt->close();

// 4) Fetch active leave types not in $assigned (CTO—12—will never be in $assigned)
if (count($assigned) > 0) {
    $placeholders = implode(',', array_fill(0, count($assigned), '?'));
    $typesSql = "
        SELECT leave_type_id, name
          FROM leave_types
         WHERE status = 'active'
           AND leave_type_id NOT IN ($placeholders)
    ";
    $stmt2 = $conn->prepare($typesSql);

    // Bind each assigned ID
    $types = str_repeat('i', count($assigned));
    $stmt2->bind_param($types, ...$assigned);
} else {
    // No non-CTO credits → return all active types (including CTO)
    $stmt2 = $conn->prepare("
        SELECT leave_type_id, name
          FROM leave_types
         WHERE status = 'active'
    ");
}

$stmt2->execute();
$types = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// 5) Return JSON array of types
echo json_encode(['success' => true, 'data' => $types]);
