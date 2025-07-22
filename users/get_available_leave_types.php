<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
  http_response_code(403);
  exit();
}

$empId = intval($_GET['employee_id'] ?? 0);
if ($empId <= 0) {
  http_response_code(400);
  exit();
}

// Fetch leave_type_ids already assigned
$stmt = $conn->prepare("
  SELECT leave_type_id 
  FROM leave_credits 
  WHERE employee_id = ?
");
$stmt->bind_param('i', $empId);
$stmt->execute();
$res = $stmt->get_result();
$assigned = array_map(fn($r)=> $r['leave_type_id'], $res->fetch_all(MYSQLI_ASSOC));
$stmt->close();

// Now fetch active leave types not in $assigned
if (count($assigned)) {
  $placeholders = implode(',', array_fill(0, count($assigned), '?'));
  $typesSql = "SELECT leave_type_id, name FROM leave_types WHERE status='active' AND leave_type_id NOT IN ($placeholders)";
  $stmt2 = $conn->prepare($typesSql);
  $stmt2->bind_param(str_repeat('i', count($assigned)), ...$assigned);
} else {
  $stmt2 = $conn->prepare("SELECT leave_type_id, name FROM leave_types WHERE status='active'");
}
$stmt2->execute();
$types = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode($types);
