<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_number = trim($_POST['employee_number'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $employment_type = $_POST['employment_type'] ?? '';
    $position = trim($_POST['position'] ?? '');
    $office = trim($_POST['office'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date_hired = $_POST['date_hired'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $password = trim($_POST['password'] ?? '');

    // Validate required fields
    if (
        empty($employee_number) || empty($first_name) || empty($last_name) ||
        empty($employment_type) || empty($position) || empty($office) ||
        empty($email) || empty($date_hired) || empty($password)
    ) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit();
    }

    $conn->begin_transaction();

    try {
        // Insert into employees
        $sqlEmp = "INSERT INTO employees (employee_number, first_name, middle_name, last_name, employment_type, position, office, email, date_hired, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtEmp = $conn->prepare($sqlEmp);
        if (!$stmtEmp) throw new Exception('Prepare employees failed: ' . $conn->error);
        $stmtEmp->bind_param("ssssssssss", $employee_number, $first_name, $middle_name, $last_name, $employment_type, $position, $office, $email, $date_hired, $status);
        $stmtEmp->execute();
        $employee_id = $conn->insert_id;
        $stmtEmp->close();

        // Insert into users table
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'employee';
        $sqlUser = "INSERT INTO users (username, email, password, role, employee_id, status) VALUES (?, ?, ?, ?, ?, 'active')";
        $stmtUser = $conn->prepare($sqlUser);
        if (!$stmtUser) throw new Exception('Prepare users failed: ' . $conn->error);
        $stmtUser->bind_param("sssis", $employee_number, $email, $hashedPassword, $role, $employee_id);
        $stmtUser->execute();
        $stmtUser->close();

        // Insert initial leave credits for VL and SL only based on employment type
        $leaveTypesResult = $conn->query("SELECT leave_type_id, name FROM leave_types WHERE status = 'active' AND (name LIKE '%Vacation%' OR name LIKE '%Sick%')");
        if (!$leaveTypesResult) throw new Exception('Failed to fetch leave types: ' . $conn->error);

        $insertLeaveCredit = $conn->prepare("INSERT INTO leave_credits (employee_id, leave_type_id, total_credits, used_credits) VALUES (?, ?, ?, 0)");
        if (!$insertLeaveCredit) throw new Exception('Prepare leave credits failed: ' . $conn->error);

        while ($row = $leaveTypesResult->fetch_assoc()) {
            $leave_type_id = $row['leave_type_id'];
            $leave_name = strtolower($row['name']);
            
            if ($employment_type === 'Teaching') {
                // For Teaching, only insert Vacation Leave with 0 credits
                if (strpos($leave_name, 'vacation') !== false) {
                    $total_credits = 0.00;
                    $insertLeaveCredit->bind_param("iid", $employee_id, $leave_type_id, $total_credits);
                    $insertLeaveCredit->execute();
                }
                // Skip Sick Leave for Teaching
            } else {
                // For Non-Teaching, insert both Vacation and Sick Leave with 0 credits
                if (strpos($leave_name, 'vacation') !== false || strpos($leave_name, 'sick') !== false) {
                    $total_credits = 0.00;
                    $insertLeaveCredit->bind_param("iid", $employee_id, $leave_type_id, $total_credits);
                    $insertLeaveCredit->execute();
                }
            }
        }
        $insertLeaveCredit->close();

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Employee and user account created successfully with initial leave credits.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
