<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$userId = (int)$_SESSION['user_id'];
$notifId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if ($notifId > 0) {
  $stmt = $conn->prepare("UPDATE notifications SET status='Read' WHERE notification_id = ? AND user_id = ?");
  $stmt->bind_param('ii', $notifId, $userId);
  $ok = $stmt->execute();
  $stmt->close();
  header('Content-Type: application/json');
  echo json_encode(['success' => $ok, 'message' => $ok ? 'Marked as read' : 'No update']);
  exit;
}

// mark all
$stmt = $conn->prepare("UPDATE notifications SET status='Read' WHERE user_id = ? AND status='Unread'");
$stmt->bind_param('i', $userId);
$ok = $stmt->execute();
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['success' => $ok, 'message' => $ok ? 'All marked as read' : 'No update']);
