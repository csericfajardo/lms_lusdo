<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$appId = (int)($_POST['application_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

$validStatuses = ['Pending', 'Approved', 'Rejected', 'Cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Get current status and details
$stmt = $conn->prepare("SELECT status, employee_id, leave_type_id, number_of_days FROM leave_applications WHERE application_id = ?");
$stmt->bind_param("i", $appId);
$stmt->execute();
$result = $stmt->get_result();
$leave = $result->fetch_assoc();
$stmt->close();

if (!$leave) {
    echo json_encode(['success' => false, 'message' => 'Leave application not found']);
    exit;
}

$previousStatus = $leave['status'];
$employee_id = $leave['employee_id'];
$leave_type_id = $leave['leave_type_id'];
$number_of_days = (float)$leave['number_of_days'];

$conn->begin_transaction();
try {
    // Update status
    $stmt = $conn->prepare("UPDATE leave_applications SET status = ?, updated_at = NOW() WHERE application_id = ?");
    $stmt->bind_param("si", $newStatus, $appId);
    $stmt->execute();
    $stmt->close();

    // Deduct leave credits if newly approved
    if ($previousStatus !== 'Approved' && $newStatus === 'Approved') {
        $stmt = $conn->prepare("SELECT used_credits, total_credits FROM leave_credits WHERE employee_id = ? AND leave_type_id = ?");
        $stmt->bind_param("ii", $employee_id, $leave_type_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $credits = $result->fetch_assoc();
        $stmt->close();

        if ($credits) {
            $new_used = $credits['used_credits'] + $number_of_days;

            if ($new_used > $credits['total_credits']) {
                throw new Exception("Insufficient leave credits.");
            }

            $stmt = $conn->prepare("UPDATE leave_credits SET used_credits = ? WHERE employee_id = ? AND leave_type_id = ?");
            $stmt->bind_param("dii", $new_used, $employee_id, $leave_type_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Leave application status updated.']);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating leave status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
