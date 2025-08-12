<?php
function notify($conn, int $userId, string $message): void {
  $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, status) VALUES (?, ?, 'Unread')");
  $stmt->bind_param('is', $userId, $message);
  $stmt->execute();
  $stmt->close();
}
