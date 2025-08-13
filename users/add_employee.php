<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once '../config/database.php';

// Only HR can add employees
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_number = trim($_POST['employee_number'] ?? '');
    $first_name      = trim($_POST['first_name'] ?? '');
    $middle_name     = trim($_POST['middle_name'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $employment_type = $_POST['employment_type'] ?? '';
    $position        = trim($_POST['position'] ?? '');
    $office          = trim($_POST['office'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $date_hired      = $_POST['date_hired'] ?? '';
    $status          = $_POST['status'] ?? 'Active';
    $password        = trim($_POST['password'] ?? '');

    // Validate required fields
    if (
        empty($employee_number) || empty($first_name) || empty($last_name) ||
        empty($employment_type) || empty($position) || empty($office) ||
        empty($email) || empty($date_hired) || empty($password)
    ) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit();
    }

    // Ensure email and username are unique
    $checkStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users WHERE email = ? OR username = ?");
    if (!$checkStmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
        exit();
    }
    $checkStmt->bind_param("ss", $email, $employee_number);
    $checkStmt->execute();
    $res = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($res['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Email or Employee Number already exists in the system.']);
        exit();
    }

    $conn->begin_transaction();

    try {
        // 1) Insert into employees table
        $sqlEmp = "INSERT INTO employees 
            (employee_number, first_name, middle_name, last_name, employment_type, position, office, email, date_hired, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtEmp = $conn->prepare($sqlEmp);
        if (!$stmtEmp) throw new Exception('Prepare employees failed: ' . $conn->error);

        $stmtEmp->bind_param(
            "ssssssssss",
            $employee_number,
            $first_name,
            $middle_name,
            $last_name,
            $employment_type,
            $position,
            $office,
            $email,
            $date_hired,
            $status
        );
        $stmtEmp->execute();
        $employee_id = $conn->insert_id;
        $stmtEmp->close();

        // 2) Ensure 'employee' is a valid ENUM in users.role
        $roleCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($roleCheck && $row = $roleCheck->fetch_assoc()) {
            if (stripos($row['Type'], "'employee'") === false) {
                throw new Exception("'employee' is not a valid ENUM value in users.role");
            }
        }

        // 3) Insert into users table
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'employee'; // Force the role

        if (empty($role)) {
            throw new Exception("Role variable is empty before insert.");
        }

        $sqlUser = "INSERT INTO users (username, email, password, role, employee_id, status) 
                    VALUES (?, ?, ?, ?, ?, 'active')";
        $stmtUser = $conn->prepare($sqlUser);
        if (!$stmtUser) throw new Exception('Prepare users failed: ' . $conn->error);

        $stmtUser->bind_param(
            "ssssi",
            $employee_number,
            $email,
            $hashedPassword,
            $role,
            $employee_id
        );
        $stmtUser->execute();
        $stmtUser->close();

        // 4) Insert initial leave credits for Vacation and Sick Leave
        $leaveTypesResult = $conn->query("
            SELECT leave_type_id, name 
            FROM leave_types 
            WHERE status = 'active' AND (name LIKE '%Vacation%' OR name LIKE '%Sick%')
        ");
        if (!$leaveTypesResult) throw new Exception('Failed to fetch leave types: ' . $conn->error);

        $insertLeaveCredit = $conn->prepare("
            INSERT INTO leave_credits (employee_id, leave_type_id, total_credits, used_credits) 
            VALUES (?, ?, ?, 0)
        ");
        if (!$insertLeaveCredit) throw new Exception('Prepare leave credits failed: ' . $conn->error);

        while ($row = $leaveTypesResult->fetch_assoc()) {
            $leave_type_id = $row['leave_type_id'];
            $leave_name    = strtolower($row['name']);
            $total_credits = 0.00;

            if ($employment_type === 'Teaching') {
                // Teaching → only Vacation Leave
                if (strpos($leave_name, 'vacation') !== false) {
                    $insertLeaveCredit->bind_param("iid", $employee_id, $leave_type_id, $total_credits);
                    $insertLeaveCredit->execute();
                }
            } else {
                // Non-Teaching → Vacation + Sick Leave
                if (strpos($leave_name, 'vacation') !== false || strpos($leave_name, 'sick') !== false) {
                    $insertLeaveCredit->bind_param("iid", $employee_id, $leave_type_id, $total_credits);
                    $insertLeaveCredit->execute();
                }
            }
        }
        $insertLeaveCredit->close();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Employee and user account created successfully with initial leave credits.'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Transaction failed: ' . $e->getMessage()
        ]);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
