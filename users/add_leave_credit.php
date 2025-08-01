<?php
session_start();
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}

$employee_id   = isset($_POST['employee_id'])   ? (int) $_POST['employee_id']   : 0;
$leave_type_id = isset($_POST['leave_type_id']) ? (int) $_POST['leave_type_id'] : 0;
$added_by      = $_SESSION['user_id'];

if ($employee_id <= 0 || $leave_type_id <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Missing employee or leave type.']);
    exit();
}

try {
    // --- CTO branch: always INSERT a new CTO earning record ---
    if ($leave_type_id === 12) {
        $source        = trim($_POST['source']         ?? '');
        $earned_at     =   $_POST['earned_at']          ?? '';
        $expires_at    =   $_POST['expires_at']         ?? '';
        $days_earned   = isset($_POST['total_credits']) 
                          ? (float) $_POST['total_credits']
                          : 0.0;
        if ($source===''|| !$earned_at|| !$expires_at|| $days_earned<=0) {
            throw new Exception('Invalid CTO fields.');
        }
        // Insert into cto_earnings
        $stmt = $conn->prepare("
            INSERT INTO cto_earnings
              (employee_id, days_earned, days_used, earned_at, expires_at, source)
            VALUES (?, ?, 0.00, ?, ?, ?)
        ");
        $stmt->bind_param('idsss',$employee_id,$days_earned,$earned_at,$expires_at,$source);
        $stmt->execute();
        $newCtoId = $stmt->insert_id;
        $stmt->close();

        // Log it
        $stmt = $conn->prepare("
            INSERT INTO leave_credit_logs
              (employee_id, leave_type_id, added_credits, reason, added_by)
            VALUES (?, 12, ?, ?, ?)
        ");
        $reason = "CTO added (id={$newCtoId}, source={$source})";
        $stmt->bind_param('idssi',$employee_id,$days_earned,$reason,$added_by);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success'=>true,'message'=>'CTO credit added.']);
        exit();
    }

    // --- Non-CTO branch: update or insert into leave_credits ---
    $added = isset($_POST['total_credits'])
             ? (float) $_POST['total_credits']
             : 0.0;
    $reason = trim($_POST['reason'] ?? '');
    if ($added <= 0 || $reason === '') {
        throw new Exception('Invalid credit amount or missing reason.');
    }

    // 1) Check for existing credits row
    $stmt = $conn->prepare("
        SELECT credit_id, total_credits
          FROM leave_credits
         WHERE employee_id = ?
           AND leave_type_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii',$employee_id,$leave_type_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        // 2a) Update existing
        $stmt = $conn->prepare("
            UPDATE leave_credits
               SET total_credits = total_credits + ?
             WHERE credit_id = ?
        ");
        $stmt->bind_param('di',$added,$row['credit_id']);
        $stmt->execute();
        $stmt->close();

        $action = "Updated leave_credits id={$row['credit_id']}";
    } else {
        // 2b) Insert new
        $stmt = $conn->prepare("
            INSERT INTO leave_credits
              (employee_id, leave_type_id, total_credits, used_credits)
            VALUES (?, ?, ?, 0.00)
        ");
        $stmt->bind_param('iid',$employee_id,$leave_type_id,$added);
        $stmt->execute();
        $newCreditId = $stmt->insert_id;
        $stmt->close();

        $action = "Inserted new leave_credits id={$newCreditId}";
    }

    // 3) Log the change
    $stmt = $conn->prepare("
        INSERT INTO leave_credit_logs
          (employee_id, leave_type_id, added_credits, reason, added_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iidsi',$employee_id,$leave_type_id,$added,$reason,$added_by);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success'=>true,'message'=>"Leave credits updated. ({$action})"]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
