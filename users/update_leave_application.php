<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

// 1) Validate application_id
$appId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
if ($appId <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid application ID.']);
    exit;
}

// 2) Fetch existing application info
$stmt = $conn->prepare(
    "SELECT employee_id, leave_type_id, status AS old_status, number_of_days AS old_days
       FROM leave_applications
      WHERE application_id = ?"
);
$stmt->bind_param('i', $appId);
$stmt->execute();
$appInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appInfo) {
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'Application not found.']);
    exit;
}

$empId       = (int)$appInfo['employee_id'];
$leaveTypeId = (int)$appInfo['leave_type_id'];
$oldStatus   = $appInfo['old_status'];
$oldDays     = (float)$appInfo['old_days'];

// 3) New submitted values
$newStatus = $_POST['status'] ?? $oldStatus;
$newDays   = isset($_POST['number_of_days']) ? (float)$_POST['number_of_days'] : $oldDays;

// 3a) If CTO-type, fetch the specific cto_id from details table
$ctoId = null;
if ($leaveTypeId === 12) {
    $stmt = $conn->prepare("
        SELECT field_value
          FROM leave_application_details
         WHERE application_id = ?
           AND field_name = 'cto_id'
         LIMIT 1
    ");
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $ctoId = (int)$row['field_value'];
    }
}

// 4) Determine transition flags
$wasApproved    = $oldStatus === 'Approved';
$nowApproved    = $newStatus === 'Approved';
$becameApproved = !$wasApproved && $nowApproved;
$becameUnappr   = $wasApproved && !$nowApproved;
$daysChanged    = $wasApproved && $nowApproved && abs($newDays - $oldDays) > 0.0001;
$diff           = $newDays - $oldDays;

// 5) Begin transaction
$conn->begin_transaction();
try {
    // 5a) Update the main application record
    $upd = $conn->prepare("
        UPDATE leave_applications
           SET number_of_days = ?, status = ?, updated_at = NOW()
         WHERE application_id = ?
    ");
    $upd->bind_param('dsi', $newDays, $newStatus, $appId);
    $upd->execute();
    $upd->close();

    // 5b) Update any other detail fields (if your form sends them)
    if (!empty($_POST['details']) && is_array($_POST['details'])) {
        $sqlDet = "
            UPDATE leave_application_details
               SET field_value = ?
             WHERE application_id = ?
               AND field_name = ?
        ";
        $detStmt = $conn->prepare($sqlDet);
        foreach ($_POST['details'] as $field => $val) {
            if (in_array($field, ['application_id','status','number_of_days'], true)) {
                continue;
            }
            $detStmt->bind_param('sis', $val, $appId, $field);
            $detStmt->execute();
        }
        $detStmt->close();
    }

    // 5c) Adjust credits
    if ($leaveTypeId === 12 && $ctoId) {
        // CTO branch
        if ($becameApproved || $becameUnappr || $daysChanged) {
            // Lock the specific CTO row
            $lock = $conn->prepare("
                SELECT days_earned, days_used
                  FROM cto_earnings
                 WHERE cto_id = ? AND employee_id = ?
                   FOR UPDATE
            ");
            $lock->bind_param('ii', $ctoId, $empId);
            $lock->execute();
            $ctoData = $lock->get_result()->fetch_assoc();
            $lock->close();

            if (!$ctoData) {
                throw new Exception("CTO record not found.");
            }

            // Compute new used
            if ($becameApproved) {
                $newUsed = $ctoData['days_used'] + $newDays;
            } elseif ($becameUnappr) {
                $newUsed = $ctoData['days_used'] - $oldDays;
            } else { // daysChanged
                $newUsed = $ctoData['days_used'] + $diff;
            }

            if ($newUsed < 0 || $newUsed > $ctoData['days_earned']) {
                throw new Exception("Invalid CTO adjustment.");
            }

            // Persist the update
            $up = $conn->prepare("
                UPDATE cto_earnings
                   SET days_used = ?
                 WHERE cto_id = ?
            ");
            $up->bind_param('di', $newUsed, $ctoId);
            $up->execute();
            $up->close();
        }
    } else {
        // Regular leave_credits branch
        if ($becameApproved || $becameUnappr || $daysChanged) {
            $delta = $becameApproved
                     ? $newDays
                     : ($becameUnappr ? -$oldDays : $diff);

            $up = $conn->prepare("
                UPDATE leave_credits
                   SET used_credits = used_credits + ?
                 WHERE employee_id = ? AND leave_type_id = ?
            ");
            $up->bind_param('dii', $delta, $empId, $leaveTypeId);
            $up->execute();
            $up->close();
        }
    }

    // 6) Commit
    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'Application updated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("update_leave_application error: " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
