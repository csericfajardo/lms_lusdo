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

$employee_id = (int)($_POST['employee_id'] ?? 0);
$leave_type_id = (int)($_POST['leave_type_id'] ?? 0);
$number_of_days = (float)($_POST['number_of_days'] ?? 0);
$status = $_POST['status'] ?? 'Pending';
$filed_by = $_SESSION['user_id'];

if ($employee_id <= 0 || $leave_type_id <= 0 || $number_of_days <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing core fields.',
        'debug' => [
            'employee_id' => $employee_id,
            'leave_type_id' => $leave_type_id,
            'number_of_days' => $number_of_days,
            'status' => $_POST['status'] ?? null,
            'raw_post' => $_POST,
            'files' => $_FILES
        ]
    ]);
    exit();
}


// Validate leave type
$stmt = $conn->prepare("SELECT name FROM leave_types WHERE leave_type_id = ?");
$stmt->bind_param("i", $leave_type_id);
$stmt->execute();
$leaveTypeResult = $stmt->get_result();
$leaveType = $leaveTypeResult->fetch_assoc();
$stmt->close();

if (!$leaveType) {
    echo json_encode(['success' => false, 'message' => 'Invalid leave type.']);
    exit();
}

$conn->begin_transaction();
try {
    // Insert leave application
    $insertApp = $conn->prepare("
        INSERT INTO leave_applications 
            (employee_id, leave_type_id, number_of_days, filed_by, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertApp->bind_param("iidds", $employee_id, $leave_type_id, $number_of_days, $filed_by, $status);

    $insertApp->execute();
    $application_id = $insertApp->insert_id;
    $insertApp->close();

    // Insert dynamic fields
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['employee_id', 'leave_type_id', 'number_of_days', 'apply_leave_type_name', 'status'])) {
            continue;
        }

        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/leave_attachments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = basename($_FILES[$key]['name']);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $newFilename = uniqid('doc_') . '.' . $extension;
            $destination = $uploadDir . $newFilename;

            move_uploaded_file($_FILES[$key]['tmp_name'], $destination);
            $value = $newFilename;
        }

        $insertField = $conn->prepare("
            INSERT INTO leave_application_details (application_id, field_name, field_value)
            VALUES (?, ?, ?)
        ");
        $insertField->bind_param("iss", $application_id, $key, $value);
        $insertField->execute();
        $insertField->close();
    }

    // Deduct credits if approved
    if ($status === 'Approved') {
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
    echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Apply Leave Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
