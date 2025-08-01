<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Access control: only HR users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Common inputs
$employee_id   = isset($_POST['employee_id'])   ? (int) $_POST['employee_id']   : 0;
$leave_type_id = isset($_POST['leave_type_id']) ? (int) $_POST['leave_type_id'] : 0;
$added_by      = $_SESSION['user_id'];

// Validate core inputs
if ($employee_id <= 0 || $leave_type_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid employee_id or leave_type_id.']);
    exit();
}

try {
    // Branch for CTO (Compensatory Time-Off)
    if ($leave_type_id === 12) {
        // Expect POST: source, earned_at, expires_at, number_of_days
        $source          = trim($_POST['source']          ?? '');
        $earned_at       = $_POST['earned_at']            ?? '';
        $expires_at      = $_POST['expires_at']           ?? '';
        $number_of_days  = isset($_POST['number_of_days'])
                           ? (float) $_POST['number_of_days']
                           : 0.0;

        // Validate CTO inputs
        if ($source === '' || !$earned_at || !$expires_at || $number_of_days <= 0) {
            throw new Exception('Missing or invalid CTO fields.');
        }

        // Insert into cto_earnings
        $stmt = $conn->prepare("
            INSERT INTO cto_earnings
                (employee_id, days_earned, days_used, earned_at, expires_at, source)
            VALUES (?, ?, 0.00, ?, ?, ?)
        ");
        $stmt->bind_param(
            'idsss',
            $employee_id,
            $number_of_days,
            $earned_at,
            $expires_at,
            $source
        );
        $stmt->execute();
        $cteoid = $stmt->insert_id;
        $stmt->close();

        // Log into leave_credit_logs for audit
        $stmt = $conn->prepare("
            INSERT INTO leave_credit_logs
                (employee_id, leave_type_id, added_credits, reason, added_by)
            VALUES (?, 12, ?, 'CTO credit added (ID: {$cteoid})', ?)
        ");
        $stmt->bind_param('idi', $employee_id, $number_of_days, $added_by);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Compensatory Time-Off credit added.']);
        exit();
    }

    // Non-CTO leave types: add to leave_credits
    $total_credits = isset($_POST['total_credits'])
                     ? (float) $_POST['total_credits']
                     : 0.0;

    if ($total_credits <= 0) {
        throw new Exception('Missing or invalid total_credits.');
    }

    // Insert into leave_credits
    $stmt = $conn->prepare("
        INSERT INTO leave_credits
            (employee_id, leave_type_id, total_credits, used_credits)
        VALUES (?, ?, ?, 0.00)
    ");
    $stmt->bind_param('iid', $employee_id, $leave_type_id, $total_credits);
    $stmt->execute();
    $newCreditId = $stmt->insert_id;
    $stmt->close();

    // Log into leave_credit_logs
    $stmt = $conn->prepare("
        INSERT INTO leave_credit_logs
            (employee_id, leave_type_id, added_credits, reason, added_by)
        VALUES (?, ?, ?, 'Initial credit setup', ?)
    ");
    $stmt->bind_param('iidi', $employee_id, $leave_type_id, $total_credits, $added_by);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Leave credits added.']);
    exit();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?>
