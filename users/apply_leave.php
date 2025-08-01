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
$filed_by       = $_SESSION['user_id'];

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

// Check leave type exists
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

    // 2) Insert dynamic details
    foreach ($_POST as $key => $value) {
        // Skip core fields
        if (in_array($key, ['employee_id','leave_type_id','number_of_days','status'])) {
            continue;
        }
        // Handle file uploads
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/leave_attachments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $origName   = basename($_FILES[$key]['name']);
            $ext        = pathinfo($origName, PATHINFO_EXTENSION);
            $newName    = uniqid('doc_') . '.' . $ext;
            $destination= $uploadDir . $newName;
            move_uploaded_file($_FILES[$key]['tmp_name'], $destination);
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
            // Fetch CTO record
            $stmtCTO = $conn->prepare(
                "SELECT days_earned, days_used FROM cto_earnings 
                 WHERE cto_id = ? AND employee_id = ?"
            );
            $stmtCTO->bind_param("ii", $cto_id, $employee_id);
            $stmtCTO->execute();
            $ctoData = $stmtCTO->get_result()->fetch_assoc();
            $stmtCTO->close();
            if (!$ctoData) {
                throw new Exception("CTO record not found.");
            }
            $new_used = $ctoData['days_used'] + $number_of_days;
            if ($new_used > $ctoData['days_earned']) {
                throw new Exception("Insufficient CTO credit for selected record.");
            }
            // Update CTO usage
            $updCTO = $conn->prepare(
                "UPDATE cto_earnings SET days_used = ? WHERE cto_id = ?"
            );
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
                $new_used = $lc['used_credits'] + $number_of_days;
                if ($new_used > $lc['total_credits']) {
                    throw new Exception("Insufficient leave credits.");
                }
                $updLC = $conn->prepare(
                    "UPDATE leave_credits SET used_credits = ? 
                     WHERE employee_id = ? AND leave_type_id = ?"
                );
                $updLC->bind_param("dii", $new_used, $employee_id, $leave_type_id);
                $updLC->execute();
                $updLC->close();
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Apply Leave Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
