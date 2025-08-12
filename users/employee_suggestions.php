<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// HR-only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$qLike = '%' . $q . '%';

$sql = "
  SELECT 
    e.employee_id,
    e.employee_number,
    CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS full_name,
    e.office
  FROM employees e
  WHERE 
    e.employee_number LIKE ?
    OR CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) LIKE ?
    OR CONCAT_WS(' ', e.last_name, e.first_name, e.middle_name) LIKE ?
    OR e.office LIKE ?
  ORDER BY e.last_name, e.first_name
  LIMIT 10
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $qLike, $qLike, $qLike, $qLike);
$stmt->execute();
$res = $stmt->get_result();

$suggestions = [];
while ($r = $res->fetch_assoc()) {
    $suggestions[] = [
        'employee_id'     => (int)$r['employee_id'],
        'employee_number' => $r['employee_number'],
        'full_name'       => $r['full_name'],
        'office'          => $r['office'],
    ];
}
$stmt->close();

header('Content-Type: application/json');
echo json_encode($suggestions);
