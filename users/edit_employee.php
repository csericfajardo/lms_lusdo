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
    $employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
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

    if (
        $employee_id <= 0 ||
        empty($employee_number) || empty($first_name) || empty($last_name) ||
        empty($employment_type) || empty($position) || empty($office) ||
        empty($email) || empty($date_hired)
    ) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields except password.']);
        exit();
    }

    $conn->begin_transaction();

    try {
        // Update employees table
        $sqlEmp = "UPDATE employees SET employee_number = ?, first_name = ?, middle_name = ?, last_name = ?, employment_type = ?, position = ?, office = ?, email = ?, date_hired = ?, status = ? WHERE employee_id = ?";
        $stmtEmp = $conn->prepare($sqlEmp);
        if (!$stmtEmp) throw new Exception('Prepare employees update failed: ' . $conn->error);
        $stmtEmp->bind_param("ssssssssssi", $employee_number, $first_name, $middle_name, $last_name, $employment_type, $position, $office, $email, $date_hired, $status, $employee_id);
        $stmtEmp->execute();
        $stmtEmp->close();

        // Update users table (email + password if set)
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sqlUser = "UPDATE users SET username = ?, email = ?, password = ? WHERE employee_id = ?";
            $stmtUser = $conn->prepare($sqlUser);
            if (!$stmtUser) throw new Exception('Prepare users update failed: ' . $conn->error);
            $stmtUser->bind_param("sssi", $employee_number, $email, $hashedPassword, $employee_id);
        } else {
            $sqlUser = "UPDATE users SET username = ?, email = ? WHERE employee_id = ?";
            $stmtUser = $conn->prepare($sqlUser);
            if (!$stmtUser) throw new Exception('Prepare users update failed: ' . $conn->error);
            $stmtUser->bind_param("ssi", $employee_number, $email, $employee_id);
        }
        $stmtUser->execute();
        $stmtUser->close();

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Employee updated successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
