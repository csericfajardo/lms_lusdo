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

// Get leave_type_id from query string
$leave_type_id = isset($_GET['leave_type_id']) ? (int) $_GET['leave_type_id'] : 0;
if ($leave_type_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid leave type.']);
    exit();
}

// Fetch leave type data
$sql = "SELECT name, required_fields 
        FROM leave_types 
        WHERE leave_type_id = ? AND status = 'active' 
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $leave_type_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Leave type not found.']);
    exit();
}

// Decode JSON required fields column
$fields = json_decode($row['required_fields'], true);
if (!is_array($fields)) {
    $fields = [];
}

header('Content-Type: application/json');
echo json_encode([
    'success'          => true,
    'leave_type_name'  => $row['name'],
    'required_fields'  => $fields
]);
