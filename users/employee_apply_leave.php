<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=UTF-8');
// NOTE: Displaying PHP errors can break JSON responses. Consider disabling in production.
// ini_set('display_errors', 0); error_reporting(E_ALL);

/* -----------------------------
   Auth: must be logged in employee
------------------------------*/
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$userId = (int)$_SESSION['user_id'];

/* Load user → get employee_id */
$sqlUser = "SELECT user_id, role, employee_id FROM users WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user || ($user['role'] !== 'employee' && empty($user['employee_id']))) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

/* Derive employee_id safely */
$employee_id = (int)$user['employee_id'];
if ($employee_id <= 0) {
    $employee_no = $_SESSION['employee_no'] ?? '';
    if ($employee_no) {
        $s = $conn->prepare("SELECT employee_id FROM employees WHERE employee_number = ? LIMIT 1");
        $s->bind_param("s", $employee_no);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) $employee_id = (int)$r['employee_id'];
    }
}
if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'No employee record linked to your account.']);
    exit();
}

/* -----------------------------
   Inputs & validation
------------------------------*/
$leave_type_id  = (int)($_POST['leave_type_id']  ?? 0);
$number_of_days = (float)($_POST['number_of_days'] ?? 0);

if ($leave_type_id <= 0 || $number_of_days <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields (leave type / number of days).'
    ]);
    exit();
}

/* Ensure leave type exists & is active */
$ltStmt = $conn->prepare("SELECT name, status FROM leave_types WHERE leave_type_id = ? LIMIT 1");
$ltStmt->bind_param("i", $leave_type_id);
$ltStmt->execute();
$lt = $ltStmt->get_result()->fetch_assoc();
$ltStmt->close();
if (!$lt || $lt['status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => 'Invalid or inactive leave type.']);
    exit();
}

/* Build a human-readable date range from submitted fields
   (so the UI can show it instantly without reloading) */
$df  = trim($_POST['date_from'] ?? '');
$dt  = trim($_POST['date_to'] ?? '');
$eff = trim($_POST['effective_date'] ?? '');
$fmt = function($d){
    $ts = strtotime($d);
    return $ts ? date('M d, Y', $ts) : '';
};
$dates_display = '—';
if ($df !== '') {
    $dates_display = $dt !== '' ? ($fmt($df) . ' – ' . $fmt($dt)) : $fmt($df);
} elseif ($eff !== '') {
    $dates_display = $fmt($eff);
}

/* -----------------------------
   Insert: application + details + notifications
------------------------------*/
$status   = 'Pending';
$filed_by = $userId;

$conn->begin_transaction();
try {
    // 1) leave_applications
    $ins = $conn->prepare("INSERT INTO leave_applications (employee_id, leave_type_id, number_of_days, filed_by, status) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param("iidds", $employee_id, $leave_type_id, $number_of_days, $filed_by, $status);
    $ins->execute();
    $application_id = $ins->insert_id;
    $ins->close();

    // 2) leave_application_details
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['leave_type_id','number_of_days'])) continue;

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
            $value = $newName; // store file name
        }

        $f = $conn->prepare("INSERT INTO leave_application_details (application_id, field_name, field_value) VALUES (?, ?, ?)");
        $f->bind_param("iss", $application_id, $key, $value);
        $f->execute();
        $f->close();
    }

    // 3) notifications
    // self
    $msgEmp = sprintf(
        "Your leave application #%d for %s (%.2f day/s) has been submitted. Status: %s.",
        $application_id, $lt['name'], $number_of_days, $status
    );
    $n1 = $conn->prepare("INSERT INTO notifications (user_id, message, status) VALUES (?, ?, 'Unread')");
    $n1->bind_param("is", $userId, $msgEmp);
    $n1->execute();
    $n1->close();

    // HRs
    $empInfoStmt = $conn->prepare("SELECT employee_number, CONCAT_WS(' ', first_name, last_name) AS emp_name FROM employees WHERE employee_id = ?");
    $empInfoStmt->bind_param("i", $employee_id);
    $empInfoStmt->execute();
    $empInfo = $empInfoStmt->get_result()->fetch_assoc();
    $empInfoStmt->close();

    $empNumber = $empInfo ? $empInfo['employee_number'] : $employee_id;
    $empName   = $empInfo ? $empInfo['emp_name']       : 'Employee';

    $hr = $conn->prepare("SELECT user_id FROM users WHERE role = 'hr' AND status = 'active'");
    $hr->execute();
    $resHR = $hr->get_result();
    if ($resHR) {
        $insN = $conn->prepare("INSERT INTO notifications (user_id, message, status) VALUES (?, ?, 'Unread')");
        while ($row = $resHR->fetch_assoc()) {
            $uid = (int)$row['user_id'];
            $msg = sprintf(
                "New leave application #%d filed by %s — %s (%.2f day/s). Status: %s.",
                $application_id, "{$empNumber} – {$empName}", $lt['name'], $number_of_days, $status
            );
            $insN->bind_param("is", $uid, $msg);
            $insN->execute();
        }
        $insN->close();
    }
    $hr->close();

    $conn->commit();

    // Build minimal payload for instant UI update
    $today = date('M d, Y');
    echo json_encode([
        'success' => true,
        'message' => 'Leave application submitted. Waiting for HR approval.',
        'application' => [
            'id'             => $application_id,        // <-- important for table ID column
            'application_id' => $application_id,        // optional alias
            'leave_type'     => $lt['name'],
            'number_of_days' => $number_of_days,
            'status'         => $status,
            'created_at'     => $today,
            'updated_at'     => $today,
            'approver'       => '—',
            'dates'          => $dates_display          // from submitted form fields
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Employee Apply Leave Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
