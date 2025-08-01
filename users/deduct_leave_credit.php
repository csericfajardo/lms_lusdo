<?php
// users/deduct_leave.php

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// 1) Access control: only HR users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}

// 2) Collect & validate inputs
$employee_id   = isset($_POST['employee_id'])   ? (int) $_POST['employee_id']   : 0;
$leave_type_id = isset($_POST['leave_type_id']) ? (int) $_POST['leave_type_id'] : 0;
$credit_id     = isset($_POST['credit_id'])     ? (int) $_POST['credit_id']     : 0;
$days          = isset($_POST['days_to_deduct'])? (float) $_POST['days_to_deduct'] : 0.0;
$reason        = trim($_POST['reason'] ?? '');
$by            = $_SESSION['user_id'];

if (
    $employee_id <= 0 ||
    $leave_type_id <= 0 ||
    $credit_id     <= 0 ||
    $days          <= 0 ||
    $reason === ''
) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Missing or invalid input.']);
    exit();
}

try {
    $conn->begin_transaction();

    if ($leave_type_id === 12) {
        // ---- CTO deduction: subtract from days_earned ----
        $stmt = $conn->prepare("
            SELECT days_earned, days_used
              FROM cto_earnings
             WHERE cto_id = ? AND employee_id = ?
               FOR UPDATE
        ");
        $stmt->bind_param('ii', $credit_id, $employee_id);
        $stmt->execute();
        $cto = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cto) {
            throw new Exception('CTO record not found.');
        }

        // ensure we don't remove credits already used
        if ($cto['days_earned'] - $days < $cto['days_used']) {
            throw new Exception('Cannot deduct beyond what remains after usage.');
        }

        $newEarned = $cto['days_earned'] - $days;
        $stmt = $conn->prepare("
            UPDATE cto_earnings
               SET days_earned = ?
             WHERE cto_id = ?
        ");
        $stmt->bind_param('di', $newEarned, $credit_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // ---- Regular leave deduction: subtract from total_credits ----
        $stmt = $conn->prepare("
            SELECT total_credits, used_credits
              FROM leave_credits
             WHERE credit_id = ? AND employee_id = ?
               FOR UPDATE
        ");
        $stmt->bind_param('ii', $credit_id, $employee_id);
        $stmt->execute();
        $lc = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$lc) {
            throw new Exception('Leave credit record not found.');
        }

        // ensure we don't dip below used_credits
        if ($lc['total_credits'] - $days < $lc['used_credits']) {
            throw new Exception('Cannot deduct beyond what has already been used.');
        }

        $newTotal = $lc['total_credits'] - $days;
        $stmt = $conn->prepare("
            UPDATE leave_credits
               SET total_credits = ?
             WHERE credit_id = ?
        ");
        $stmt->bind_param('di', $newTotal, $credit_id);
        $stmt->execute();
        $stmt->close();
    }

    // 3) Log the deduction (negative added_credits)
    $neg = -abs($days);
    $stmt = $conn->prepare("
        INSERT INTO leave_credit_logs
            (employee_id, leave_type_id, added_credits, reason, added_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iidsi', $employee_id, $leave_type_id, $neg, $reason, $by);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'Credits deducted successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log('Deduct Leave Error: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Error: ' . $e->getMessage()]);
}
