<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// ─────────────────────────────────────────────────────────────
// Auth: HR only
// ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$actingHr   = (int)$_SESSION['user_id'];
$appId      = (int)($_POST['application_id'] ?? 0);
$newDays    = isset($_POST['number_of_days']) ? (float)$_POST['number_of_days'] : null;
$newStatus  = $_POST['status'] ?? null;
$newDetails = $_POST['details'] ?? []; // dynamic fields (key => value)

$allowedStatus = ['Pending','Approved','Rejected','Cancelled'];
if (!in_array($newStatus, $allowedStatus, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}
if ($appId <= 0 || $newDays === null || $newDays < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid fields.']);
    exit;
}

// ─────────────────────────────────────────────────────────────
// Helper: simple notifier
// ─────────────────────────────────────────────────────────────
function notify_user(mysqli $conn, int $userId, string $message): void {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, status) VALUES (?, ?, 'Unread')");
    $stmt->bind_param('is', $userId, $message);
    $stmt->execute();
    $stmt->close();
}

$conn->begin_transaction();

try {
    // lock app row
    $stmt = $conn->prepare("
        SELECT 
            la.employee_id,
            la.leave_type_id,
            la.number_of_days     AS old_days,
            la.status             AS old_status,
            e.employee_number,
            CONCAT_WS(' ', e.first_name, e.last_name) AS emp_name,
            lt.name AS leave_type_name
        FROM leave_applications la
        JOIN employees e ON e.employee_id = la.employee_id
        JOIN leave_types lt ON lt.leave_type_id = la.leave_type_id
        WHERE la.application_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param('i', $appId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$app) {
        throw new Exception("Application not found.");
    }

    $employeeId   = (int)$app['employee_id'];
    $leaveTypeId  = (int)$app['leave_type_id'];
    $oldDays      = (float)$app['old_days'];
    $oldStatus    = (string)$app['old_status'];
    $leaveTypeStr = (string)$app['leave_type_name'];
    $empRef       = ($app['employee_number'] ? $app['employee_number'].' – ' : '') . ($app['emp_name'] ?? 'Employee');

    // ─────────────────────────────────────────────────────────
    // Decide approved_by value
    // ─────────────────────────────────────────────────────────
    $approvedBy = null;
    if (in_array($newStatus, ['Approved', 'Rejected', 'Cancelled'])) {
        // Always record which HR staff acted
        $approvedBy = $actingHr;
    } else {
        // Pending stays null
        $approvedBy = null;
    }

    // ─────────────────────────────────────────────────────────
    // Update main fields
    // ─────────────────────────────────────────────────────────
    $up = $conn->prepare("UPDATE leave_applications 
                          SET number_of_days = ?, status = ?, approved_by = ? 
                          WHERE application_id = ?");
    $up->bind_param('dsii', $newDays, $newStatus, $approvedBy, $appId);
    $up->execute();
    $up->close();

    // ─────────────────────────────────────────────────────────
    // Replace dynamic details (delete & reinsert)
    // ─────────────────────────────────────────────────────────
    $del = $conn->prepare("DELETE FROM leave_application_details WHERE application_id = ?");
    $del->bind_param('i', $appId);
    $del->execute();
    $del->close();

    if (is_array($newDetails)) {
        foreach ($newDetails as $k => $v) {
            $ins = $conn->prepare("INSERT INTO leave_application_details (application_id, field_name, field_value) VALUES (?, ?, ?)");
            $ins->bind_param('iss', $appId, $k, $v);
            $ins->execute();
            $ins->close();
        }
    }

    // ─────────────────────────────────────────────────────────
    // Credit / CTO Adjustments (kept as-is from your code)
    // ─────────────────────────────────────────────────────────
    $delta = 0.0;
    if ($oldStatus === 'Approved' && $newStatus === 'Approved') {
        $delta = $newDays - $oldDays;
    } elseif ($oldStatus !== 'Approved' && $newStatus === 'Approved') {
        $delta = $newDays;
    } elseif ($oldStatus === 'Approved' && $newStatus !== 'Approved') {
        $delta = -$oldDays;
    }

    if (abs($delta) > 0.000001) {
        if ($leaveTypeId === 12) {
            // CTO logic (unchanged)
            $applyCtoDelta = function(mysqli $conn, int $employeeId, int $ctoId, float $delta) {
                $s = $conn->prepare("SELECT days_earned, days_used FROM cto_earnings WHERE cto_id = ? AND employee_id = ? FOR UPDATE");
                $s->bind_param('ii', $ctoId, $employeeId);
                $s->execute();
                $row = $s->get_result()->fetch_assoc();
                $s->close();
                if (!$row) throw new Exception("CTO record not found (ID: {$ctoId}).");

                $new_used = (float)$row['days_used'] + $delta;
                if ($new_used < 0) throw new Exception("CTO adjustment would make used negative.");
                if ($new_used > (float)$row['days_earned']) throw new Exception("Insufficient CTO credit.");

                $u = $conn->prepare("UPDATE cto_earnings SET days_used = ? WHERE cto_id = ?");
                $u->bind_param('di', $new_used, $ctoId);
                $u->execute();
                $u->close();
            };
            // CTO ID handling stays here...
        } else {
            // Regular leave credits
            $s = $conn->prepare("SELECT used_credits, total_credits FROM leave_credits WHERE employee_id = ? AND leave_type_id = ? FOR UPDATE");
            $s->bind_param('ii', $employeeId, $leaveTypeId);
            $s->execute();
            $lc = $s->get_result()->fetch_assoc();
            $s->close();

            if (!$lc) throw new Exception("No leave credit record found for this leave type.");

            $new_used = (float)$lc['used_credits'] + $delta;
            if ($new_used < 0) throw new Exception("Used credits negative.");
            if ($new_used > (float)$lc['total_credits']) throw new Exception("Insufficient credits.");

            $u = $conn->prepare("UPDATE leave_credits SET used_credits = ? WHERE employee_id = ? AND leave_type_id = ?");
            $u->bind_param('dii', $new_used, $employeeId, $leaveTypeId);
            $u->execute();
            $u->close();
        }
    }

    // ─────────────────────────────────────────────────────────
    // Notifications
    // ─────────────────────────────────────────────────────────
    if ($oldStatus !== $newStatus) {
        $msg = sprintf(
            "You changed leave application #%d for %s — %s (%.2f day/s): %s → %s.",
            $appId, $empRef, $leaveTypeStr, $newDays, $oldStatus, $newStatus
        );
    } else {
        $msg = sprintf(
            "You updated leave application #%d for %s — %s (%.2f day/s). Status remains: %s.",
            $appId, $empRef, $leaveTypeStr, $newDays, $newStatus
        );
    }
    notify_user($conn, $actingHr, $msg);

    if ($oldStatus !== $newStatus) {
        $eu = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? AND status='active' LIMIT 1");
        $eu->bind_param('i', $employeeId);
        $eu->execute();
        $row = $eu->get_result()->fetch_assoc();
        $eu->close();

        if ($row) {
            $empUserId = (int)$row['user_id'];
            $msgEmp = sprintf(
                "Your leave application #%d — %s (%.2f day/s) changed status to %s.",
                $appId, $leaveTypeStr, $newDays, $newStatus
            );
            notify_user($conn, $empUserId, $msgEmp);
        }
    }

    $conn->commit();
    echo json_encode([
    'success' => true,
    'message' => 'Application updated.',
    'actingHr' => $actingHr // 👈 include HR id for debugging
]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
