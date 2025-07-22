<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Allow only HR users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$employee_id = intval($_POST['employee_id'] ?? 0);
if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
    exit();
}

$conn->begin_transaction();

try {
    // 1) Delete leave credit logs
    $stmt = $conn->prepare("DELETE FROM leave_credit_logs WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->close();

    // 2) Delete leave credits
    $stmt = $conn->prepare("DELETE FROM leave_credits WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->close();

    // 3) Delete leave applications (cascade deletes details)
    $stmt = $conn->prepare("DELETE FROM leave_applications WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->close();

    // 4) Find any user accounts for this employee
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($users)) {
        $userIds = array_column($users, 'user_id');
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($userIds));

        // 5) Delete notifications for these user accounts
        $sql = "DELETE FROM notifications WHERE user_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$userIds);
        $stmt->execute();
        $stmt->close();

        // 6) Delete user records
        $stmt = $conn->prepare("DELETE FROM users WHERE employee_id = ?");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $stmt->close();
    }

    // 7) Delete the employee record itself
    $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $conn->commit();

    if ($affected > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Employee and all related data deleted successfully.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No employee found to delete.'
        ]);
    }
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error deleting employee {$employee_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Deletion failed: ' . $e->getMessage()
    ]);
}
