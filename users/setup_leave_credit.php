<?php
session_start();
require_once '../config/database.php';

// Enable full error reporting (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$debug = [];

// 1) Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized',
        'debug'   => ['Auth failed: role='.$_SESSION['role'] ?? 'none']
    ]);
    exit();
}
$debug[] = 'Auth passed for user '.$_SESSION['user_id'];

// 2) Validate inputs
$empId        = intval($_POST['employee_id']   ?? 0);
$typeId       = intval($_POST['leave_type_id'] ?? 0);
$totalCredits = isset($_POST['total_credits']) ? floatval($_POST['total_credits']) : null;
$reason       = trim($_POST['reason']          ?? '');

if ($empId <= 0 || $typeId <= 0 || $totalCredits === null || $totalCredits < 0 || $reason === '') {
    http_response_code(400);
    $debug[] = 'Validation failed: empId='.$empId.' typeId='.$typeId.' totalCredits='.$totalCredits.' reason="'.substr($reason,0,20).'"';
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input',
        'debug'   => $debug
    ]);
    exit();
}
$debug[] = "Inputs OK: empId={$empId}, typeId={$typeId}, totalCredits={$totalCredits}";

// 3) Check existing
$chk = $conn->prepare("SELECT 1 FROM leave_credits WHERE employee_id=? AND leave_type_id=?");
if (!$chk) {
    $debug[] = 'Prepare failed (check existing): '.$conn->error;
    echo json_encode(['success'=>false,'message'=>'DB prepare error','debug'=>$debug]);
    exit();
}
$chk->bind_param('ii', $empId, $typeId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
    $debug[] = 'Leave type already exists for this employee';
    echo json_encode(['success'=>false,'message'=>'Leave type already set up','debug'=>$debug]);
    exit();
}
$debug[] = 'No existing credit found';
$chk->close();

// 4) Insert leave_credits
$ins = $conn->prepare("
    INSERT INTO leave_credits (employee_id, leave_type_id, total_credits, used_credits)
    VALUES (?, ?, ?, 0)
");
if (!$ins) {
    $debug[] = 'Prepare failed (insert credit): '.$conn->error;
    echo json_encode(['success'=>false,'message'=>'DB prepare error','debug'=>$debug]);
    exit();
}
if (! $ins->bind_param('iid', $empId, $typeId, $totalCredits) ) {
    $debug[] = 'bind_param failed: '.$ins->error;
}
if (! $ins->execute() ) {
    $debug[] = 'Execute failed (insert credit): '.$ins->error;
    echo json_encode(['success'=>false,'message'=>'DB execute error on credits insert','debug'=>$debug]);
    exit();
}
$debug[] = 'Inserted leave_credits, ID='.$ins->insert_id;
$ins->close();

// 5) Log in leave_credit_logs
$log = $conn->prepare("
    INSERT INTO leave_credit_logs (employee_id, leave_type_id, added_credits, reason, added_by)
    VALUES (?, ?, ?, ?, ?)
");
if (!$log) {
    $debug[] = 'Prepare failed (log): '.$conn->error;
    echo json_encode(['success'=>false,'message'=>'DB prepare error','debug'=>$debug]);
    exit();
}
$addedBy = $_SESSION['user_id'];
if (! $log->bind_param('iidsi', $empId, $typeId, $totalCredits, $reason, $addedBy) ) {
    $debug[] = 'bind_param failed (log): '.$log->error;
}
if (! $log->execute() ) {
    $debug[] = 'Execute failed (log): '.$log->error;
    echo json_encode(['success'=>false,'message'=>'DB execute error on log insert','debug'=>$debug]);
    exit();
}
$debug[] = 'Inserted leave_credit_logs, ID='.$log->insert_id;
$log->close();

// 6) Success
echo json_encode([
    'success' => true,
    'message' => 'Leave credit setup completed.',
    'debug'   => $debug
]);
