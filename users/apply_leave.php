<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Must be logged in as HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$employee_id    = (int)($_POST['employee_id']    ?? 0);
$leave_type_id  = (int)($_POST['leave_type_id']  ?? 0);
$number_of_days = (float)($_POST['number_of_days'] ?? 0);
$status         = $_POST['status']               ?? 'Pending';
$filed_by       = (int)$_SESSION['user_id'];

// Validate core fields
if ($employee_id <= 0 || $leave_type_id <= 0 || $number_of_days <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing core fields.',
        'debug' => [
            'employee_id'    => $employee_id,
            'leave_type_id'  => $leave_type_id,
            'number_of_days' => $number_of_days,
            'status'         => $_POST['status'] ?? null,
            'raw_post'       => $_POST,
            'files'          => $_FILES
        ]
    ]);
    exit();
}

// Check leave type exists + fetch its name
$stmt = $conn->prepare("SELECT name FROM leave_types WHERE leave_type_id = ?");
$stmt->bind_param("i", $leave_type_id);
$stmt->execute();
$res = $stmt->get_result();
$leaveType = $res->fetch_assoc();
$stmt->close();

if (!$leaveType) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid leave type.']);
    exit();
}

$conn->begin_transaction();

try {
    // 1) Insert into leave_applications
    $insertApp = $conn->prepare(
        "INSERT INTO leave_applications 
            (employee_id, leave_type_id, number_of_days, filed_by, status)
         VALUES (?, ?, ?, ?, ?)"
    );
    $insertApp->bind_param("iidds", $employee_id, $leave_type_id, $number_of_days, $filed_by, $status);
    $insertApp->execute();
    $application_id = $insertApp->insert_id;
    $insertApp->close();

    // 2) Insert dynamic details (including uploaded files -> saved filename)
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['employee_id','leave_type_id','number_of_days','status'])) continue;

        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/leave_attachments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $origName    = basename($_FILES[$key]['name']);
            $ext         = pathinfo($origName, PATHINFO_EXTENSION);
            $newName     = uniqid('doc_') . ($ext ? '.' . $ext : '');
            $destination = $uploadDir . $newName;

            if (!move_uploaded_file($_FILES[$key]['tmp_name'], $destination)) {
                throw new Exception("Failed to save uploaded file for field {$key}.");
            }
            $value = $newName;
        }

        $insertField = $conn->prepare(
            "INSERT INTO leave_application_details (application_id, field_name, field_value)
             VALUES (?, ?, ?)"
        );
        $insertField->bind_param("iss", $application_id, $key, $value);
        $insertField->execute();
        $insertField->close();
    }

    // 3) Deduct credits if approved
    if ($status === 'Approved') {
        if ($leave_type_id === 12 && isset($_POST['cto_id'])) {
            // CTO-specific deduction
            $cto_id = (int) $_POST['cto_id'];

            $stmtCTO = $conn->prepare(
                "SELECT days_earned, days_used FROM cto_earnings 
                 WHERE cto_id = ? AND employee_id = ?"
            );
            $stmtCTO->bind_param("ii", $cto_id, $employee_id);
            $stmtCTO->execute();
            $ctoData = $stmtCTO->get_result()->fetch_assoc();
            $stmtCTO->close();

            if (!$ctoData) throw new Exception("CTO record not found.");

            $new_used = (float)$ctoData['days_used'] + $number_of_days;
            if ($new_used > (float)$ctoData['days_earned']) {
                throw new Exception("Insufficient CTO credit for selected record.");
            }

            $updCTO = $conn->prepare("UPDATE cto_earnings SET days_used = ? WHERE cto_id = ?");
            $updCTO->bind_param("di", $new_used, $cto_id);
            $updCTO->execute();
            $updCTO->close();
        } else {
            // Regular leave deduction
            $stmtLC = $conn->prepare(
                "SELECT used_credits, total_credits 
                 FROM leave_credits 
                 WHERE employee_id = ? AND leave_type_id = ?"
            );
            $stmtLC->bind_param("ii", $employee_id, $leave_type_id);
            $stmtLC->execute();
            $lc = $stmtLC->get_result()->fetch_assoc();
            $stmtLC->close();

            if ($lc) {
                $new_used = (float)$lc['used_credits'] + $number_of_days;
                if ($new_used > (float)$lc['total_credits']) {
                    throw new Exception("Insufficient leave credits.");
                }
                $updLC = $conn->prepare(
                    "UPDATE leave_credits SET used_credits = ? 
                     WHERE employee_id = ? AND leave_type_id = ?"
                );
                $updLC->bind_param("dii", $new_used, $employee_id, $leave_type_id);
                $updLC->execute();
                $updLC->close();
            } else {
                throw new Exception("No leave credit record found for this type.");
            }
        }
    }

    // 4) Notifications
    // 4a) Find the user_id of the employee (if they have an account)
    $employeeUserId = null;
    $stmtEmpUser = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND status = 'active' LIMIT 1");
    $stmtEmpUser->bind_param("i", $employee_id);
    $stmtEmpUser->execute();
    $resEmpUser = $stmtEmpUser->get_result()->fetch_assoc();
    $stmtEmpUser->close();
    if ($resEmpUser) $employeeUserId = (int)$resEmpUser['user_id'];

    // 4b) Get all other HR users (active) to notify, excluding current HR filer
    $hrUserIds = [];
    $stmtHR = $conn->prepare("SELECT user_id FROM users WHERE role = 'hr' AND status = 'active' AND user_id <> ?");
    $stmtHR->bind_param("i", $filed_by);
    $stmtHR->execute();
    $resHR = $stmtHR->get_result();
    while ($r = $resHR->fetch_assoc()) {
        $hrUserIds[] = (int)$r['user_id'];
    }
    $stmtHR->close();

    // 4c) Helpful info for message: employee number + name
    $empInfoStmt = $conn->prepare("SELECT employee_number, CONCAT_WS(' ', first_name, last_name) AS emp_name FROM employees WHERE employee_id = ?");
    $empInfoStmt->bind_param("i", $employee_id);
    $empInfoStmt->execute();
    $empInfo = $empInfoStmt->get_result()->fetch_assoc();
    $empInfoStmt->close();

    $empNumber = $empInfo ? $empInfo['employee_number'] : $employee_id;
    $empName   = $empInfo ? $empInfo['emp_name']       : 'Employee';

    // 4d) Prepare insert for notifications
    $insNotif = $conn->prepare("INSERT INTO notifications (user_id, message, status) VALUES (?, ?, 'Unread')");

    // Notify the employee (if they have a user record)
    if ($employeeUserId !== null) {
        $msgEmp = sprintf(
            "Your leave application #%d for %s (%.2f day/s) has been submitted. Status: %s.",
            $application_id, $leaveType['name'], $number_of_days, $status
        );
        $insNotif->bind_param("is", $employeeUserId, $msgEmp);
        $insNotif->execute();
    }

    // Notify other HR users
    if (!empty($hrUserIds)) {
        foreach ($hrUserIds as $uid) {
            $msgHR = sprintf(
                "New/updated leave application #%d filed for %s — %s (%.2f day/s). Status: %s.",
                $application_id, "{$empNumber} – {$empName}", $leaveType['name'], $number_of_days, $status
            );
            $insNotif->bind_param("is", $uid, $msgHR);
            $insNotif->execute();
        }
    }
    $insNotif->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Apply Leave Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
