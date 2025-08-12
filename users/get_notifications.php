<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$userId = (int)$_SESSION['user_id'];

// optional: filter by status
$status = $_GET['status'] ?? ''; // '', 'Unread', 'Read'
$limit  = max(1, min(100, (int)($_GET['limit'] ?? 50)));

$sql = "
  SELECT notification_id, message, status, created_at
  FROM notifications
  WHERE user_id = ?
";
$params = [$userId];
$types  = 'i';

if ($status === 'Unread' || $status === 'Read') {
  $sql .= " AND status = ? ";
  $params[] = $status;
  $types   .= 's';
}

$sql .= " ORDER BY (status='Unread') DESC, created_at DESC, notification_id DESC LIMIT ? ";
$params[] = $limit;
$types   .= 'i';

$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Prepare failed']);
  exit;
}

// --- bind WITH REFERENCES ---
$bindParams = [];
$bindParams[] = &$types;
for ($i = 0; $i < count($params); $i++) {
  $bindParams[] = &$params[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bindParams);

$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
  $rows[] = [
    'notification_id' => (int)$r['notification_id'],
    'message'         => $r['message'],
    'status'          => $r['status'],
    'created_at'      => $r['created_at'],
  ];
}
$stmt->close();

// badge count
$stmt2 = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND status='Unread'");
$stmt2->bind_param('i', $userId);
$stmt2->execute();
$unreadRes = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$unread = isset($unreadRes['c']) ? (int)$unreadRes['c'] : 0;

echo json_encode(['success' => true, 'unread' => $unread, 'data' => $rows]);
