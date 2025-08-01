<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 1) Validate inputs
$appId     = (int)($_POST['application_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

$valid = ['Pending','Approved','Rejected','Cancelled'];
if ($appId <= 0 || !in_array($newStatus, $valid, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// 2) Fetch existing application
$stmt = $conn->prepare("
    SELECT status AS old_status, employee_id, leave_type_id, number_of_days
      FROM leave_applications
     WHERE application_id = ?
");
$stmt->bind_param("i", $appId);
$stmt->execute();
$leave = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leave) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit;
}

$oldStatus     = $leave['old_status'];
$empId         = (int)$leave['employee_id'];
$leaveTypeId   = (int)$leave['leave_type_id'];
$days          = (float)$leave['number_of_days'];

// 3) If CTO, grab the cto_id from details (if present)
$ctoId = null;
if ($leaveTypeId === 12) {
    $stmt = $conn->prepare("
        SELECT field_value 
          FROM leave_application_details 
         WHERE application_id = ? 
           AND field_name = 'cto_id'
        LIMIT 1
    ");
    $stmt->bind_param("i", $appId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $ctoId = (int)$row['field_value'];
    }
}

$conn->begin_transaction();
try {
    // 4) Update application status
    $upd = $conn->prepare("
        UPDATE leave_applications 
           SET status = ?, updated_at = NOW() 
         WHERE application_id = ?
    ");
    $upd->bind_param("si", $newStatus, $appId);
    $upd->execute();
    $upd->close();

    // 5) Determine if we need to refund credits
    $wasApproved    = $oldStatus === 'Approved';
    $nowApproved    = $newStatus === 'Approved';

    // a) Newly approved → deduct (existing code)
    if (!$wasApproved && $nowApproved) {
        if ($leaveTypeId === 12 && $ctoId) {
            // CTO: add to days_used
            $stmtC = $conn->prepare("
                UPDATE cto_earnings
                   SET days_used = days_used + ?
                 WHERE cto_id = ? AND employee_id = ?
            ");
            $stmtC->bind_param("dii", $days, $ctoId, $empId);
            $stmtC->execute();
            $stmtC->close();
        } else {
            // Regular: add to used_credits
            $stmtL = $conn->prepare("
                UPDATE leave_credits
                   SET used_credits = used_credits + ?
                 WHERE employee_id = ? AND leave_type_id = ?
            ");
            $stmtL->bind_param("dii", $days, $empId, $leaveTypeId);
            $stmtL->execute();
            $stmtL->close();
        }
    }
    // b) Was approved but no longer → refund
    elseif ($wasApproved && !$nowApproved) {
        if ($leaveTypeId === 12 && $ctoId) {
            // CTO: subtract from days_used
            $stmtC = $conn->prepare("
                UPDATE cto_earnings
                   SET days_used = GREATEST(days_used - ?, 0)
                 WHERE cto_id = ? AND employee_id = ?
            ");
            $stmtC->bind_param("dii", $days, $ctoId, $empId);
            $stmtC->execute();
            $stmtC->close();
        } else {
            // Regular: subtract from used_credits
            $stmtL = $conn->prepare("
                UPDATE leave_credits
                   SET used_credits = GREATEST(used_credits - ?, 0)
                 WHERE employee_id = ? AND leave_type_id = ?
            ");
            $stmtL->bind_param("dii", $days, $empId, $leaveTypeId);
            $stmtL->execute();
            $stmtL->close();
        }
    }
    // c) Still approved but days changed => adjust by diff
    elseif ($wasApproved && $nowApproved && isset($_POST['number_of_days'])) {
        $newDays = (float)$_POST['number_of_days'];
        $diff    = $newDays - $days;
        if (abs($diff) > 0.0001) {
            if ($leaveTypeId === 12 && $ctoId) {
                $stmtC = $conn->prepare("
                    UPDATE cto_earnings
                       SET days_used = days_used + ?
                     WHERE cto_id = ? AND employee_id = ?
                ");
                $stmtC->bind_param("dii", $diff, $ctoId, $empId);
                $stmtC->execute();
                $stmtC->close();
            } else {
                $stmtL = $conn->prepare("
                    UPDATE leave_credits
                       SET used_credits = used_credits + ?
                     WHERE employee_id = ? AND leave_type_id = ?
                ");
                $stmtL->bind_param("dii", $diff, $empId, $leaveTypeId);
                $stmtL->execute();
                $stmtL->close();
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Leave application status updated.']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating leave status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
