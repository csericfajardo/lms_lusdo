<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$data = $_POST;
$appId = (int)($data['application_id'] ?? 0);
if ($appId <= 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid application ID.']);
    exit;
}

// 1) Fetch the employee_id & leave_type_id for this application
$sqlFetch = "SELECT employee_id, leave_type_id, status AS old_status, number_of_days AS old_days 
             FROM leave_applications 
             WHERE application_id = ?";
$stmtFetch = $conn->prepare($sqlFetch);
$stmtFetch->bind_param('i', $appId);
$stmtFetch->execute();
$appInfo = $stmtFetch->get_result()->fetch_assoc();
$stmtFetch->close();

if (!$appInfo) {
    echo json_encode(['success'=>false,'message'=>'Application not found.']);
    exit;
}

$empId       = (int)$appInfo['employee_id'];
$leaveTypeId = (int)$appInfo['leave_type_id'];
$oldStatus   = $appInfo['old_status'];
$oldDays     = (float)$appInfo['old_days'];
$newStatus   = $data['status'];
$newDays     = (float)$data['number_of_days'];

// 2) Update main record
$sqlUpdateApp = "UPDATE leave_applications 
                 SET number_of_days = ?, status = ?, updated_at = NOW() 
                 WHERE application_id = ?";
$stmtApp = $conn->prepare($sqlUpdateApp);
$stmtApp->bind_param('dsi', $newDays, $newStatus, $appId);
if (!$stmtApp->execute()) {
    echo json_encode(['success'=>false,'message'=>'Failed to update application.']);
    exit;
}
$stmtApp->close();

// 3) Update each detail field
if (!empty($data['details']) && is_array($data['details'])) {
    $sqlDet = "UPDATE leave_application_details 
               SET field_value = ? 
               WHERE application_id = ? AND field_name = ?";
    $stmtDet = $conn->prepare($sqlDet);
    foreach ($data['details'] as $field => $val) {
        $stmtDet->bind_param('sis', $val, $appId, $field);
        $stmtDet->execute();
    }
    $stmtDet->close();
}

// 4) Adjust leave_credits if status has just become Approved
if ($oldStatus !== 'Approved' && $newStatus === 'Approved') {
    // Increment used_credits by the approved days
    $sqlCredit = "UPDATE leave_credits 
                  SET used_credits = used_credits + ? 
                  WHERE employee_id = ? AND leave_type_id = ?";
    $stmtCredit = $conn->prepare($sqlCredit);
    $stmtCredit->bind_param('dii', $newDays, $empId, $leaveTypeId);
    $stmtCredit->execute();
    $stmtCredit->close();
}
// 5) If status was Approved and is now changed away, roll back the credit
elseif ($oldStatus === 'Approved' && $newStatus !== 'Approved') {
    $sqlCredit = "UPDATE leave_credits 
                  SET used_credits = used_credits - ? 
                  WHERE employee_id = ? AND leave_type_id = ?";
    $stmtCredit = $conn->prepare($sqlCredit);
    $stmtCredit->bind_param('dii', $oldDays, $empId, $leaveTypeId);
    $stmtCredit->execute();
    $stmtCredit->close();
}
// 6) If still Approved but days changed, adjust the difference
elseif ($oldStatus === 'Approved' && $newStatus === 'Approved' && $newDays !== $oldDays) {
    $diff = $newDays - $oldDays;
    $sqlCredit = "UPDATE leave_credits 
                  SET used_credits = used_credits + ? 
                  WHERE employee_id = ? AND leave_type_id = ?";
    $stmtCredit = $conn->prepare($sqlCredit);
    $stmtCredit->bind_param('dii', $diff, $empId, $leaveTypeId);
    $stmtCredit->execute();
    $stmtCredit->close();
}

echo json_encode(['success'=>true,'message'=>'Application updated successfully.']);
