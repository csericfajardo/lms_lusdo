<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$employee_id = (int) ($_POST['employee_id'] ?? 0);
$leave_type_id = (int) ($_POST['leave_type_id'] ?? 0);
$total_credits = (float) ($_POST['total_credits'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$added_by = $_SESSION['user_id'] ?? null;


if ($employee_id <= 0 || $leave_type_id <= 0 || $total_credits <= 0 || !$reason || !$added_by) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
    
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if a record already exists
    $checkStmt = $conn->prepare("SELECT credit_id, total_credits, used_credits FROM leave_credits WHERE employee_id = ? AND leave_type_id = ?");
    $checkStmt->bind_param("ii", $employee_id, $leave_type_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing
        $existing = $result->fetch_assoc();
        $new_total = $existing['total_credits'] + $total_credits;

        $updateStmt = $conn->prepare("UPDATE leave_credits SET total_credits = ?, updated_at = NOW() WHERE credit_id = ?");
        $updateStmt->bind_param("di", $new_total, $existing['credit_id']);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Insert new
        $insertStmt = $conn->prepare("INSERT INTO leave_credits (employee_id, leave_type_id, total_credits, used_credits) VALUES (?, ?, ?, 0)");
        $insertStmt->bind_param("iid", $employee_id, $leave_type_id, $total_credits);
        $insertStmt->execute();
        $insertStmt->close();
    }

    // Log to leave_credit_logs
    $logStmt = $conn->prepare("INSERT INTO leave_credit_logs (employee_id, leave_type_id, added_credits, reason, added_by) VALUES (?, ?, ?, ?, ?)");
    $logStmt->bind_param("iidsi", $employee_id, $leave_type_id, $total_credits, $reason, $added_by);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Leave credit successfully added.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
