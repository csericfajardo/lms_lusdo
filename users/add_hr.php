<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

header('Content-Type: application/json');

// ── Auth guard ──
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit();
}

// ── Method guard ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit();
}

// ── Collect & sanitize inputs ──
$username        = trim($_POST['username'] ?? '');
$email           = trim($_POST['email'] ?? '');
$password        = trim($_POST['password'] ?? '');
$employee_number = trim($_POST['employee_number'] ?? '');
$first_name      = trim($_POST['first_name'] ?? '');
$middle_name     = trim($_POST['middle_name'] ?? '');
$last_name       = trim($_POST['last_name'] ?? '');
$position        = trim($_POST['position'] ?? '');
$office          = trim($_POST['office'] ?? '');
$employment_type = trim($_POST['employment_type'] ?? '');
$date_hired      = trim($_POST['date_hired'] ?? '');
$status          = trim($_POST['status'] ?? 'Active');

// ── Basic validation ──
if (!$username || !$email || !$employee_number || !$first_name || !$last_name || !$position || !$office || !$date_hired) {
    echo json_encode(['success'=>false,'message'=>'Missing required fields.']);
    exit();
}

try {
    $conn->begin_transaction();

    // ── 1. Check if employee already exists by email ──
    $empId = null;
    $empCheck = $conn->prepare("SELECT employee_id FROM employees WHERE email=? LIMIT 1");
    $empCheck->bind_param("s", $email);
    $empCheck->execute();
    $empCheck->bind_result($empId);
    $empCheck->fetch();
    $empCheck->close();

    if ($empId) {
        // ── Update existing employee ──
        $empSql = "UPDATE employees 
                      SET employee_number=?, first_name=?, middle_name=?, last_name=?, 
                          employment_type=?, position=?, office=?, date_hired=?, status=? 
                    WHERE employee_id=?";
        $empStmt = $conn->prepare($empSql);
        $empStmt->bind_param(
            "sssssssssi",
            $employee_number, $first_name, $middle_name, $last_name,
            $employment_type, $position, $office, $date_hired, $status,
            $empId
        );
        $empStmt->execute();
        $empStmt->close();
    } else {
        // ── Insert new employee ──
        $empSql = "INSERT INTO employees 
                      (employee_number, first_name, middle_name, last_name, employment_type, position, office, email, date_hired, status) 
                   VALUES (?,?,?,?,?,?,?,?,?,?)";
        $empStmt = $conn->prepare($empSql);
        $empStmt->bind_param(
            "ssssssssss",
            $employee_number,$first_name,$middle_name,$last_name,
            $employment_type,$position,$office,$email,$date_hired,$status
        );
        $empStmt->execute();
        $empId = $empStmt->insert_id;
        $empStmt->close();
    }

    // ── 2. Check if user already exists with this email ──
    $userId = null;
    $userCheck = $conn->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1");
    $userCheck->bind_param("s", $email);
    $userCheck->execute();
    $userCheck->bind_result($userId);
    $userCheck->fetch();
    $userCheck->close();

    if ($userId) {
        // ── Update existing user ──
        if ($password !== '') {
            $hashed = password_hash($password,PASSWORD_DEFAULT);
            $userSql = "UPDATE users 
                           SET username=?, password=?, role='hr', status='active', employee_id=? 
                         WHERE user_id=?";
            $userStmt = $conn->prepare($userSql);
            $userStmt->bind_param("ssii", $username,$hashed,$empId,$userId);
        } else {
            $userSql = "UPDATE users 
                           SET username=?, role='hr', status='active', employee_id=? 
                         WHERE user_id=?";
            $userStmt = $conn->prepare($userSql);
            $userStmt->bind_param("sii", $username,$empId,$userId);
        }
        $userStmt->execute();
        $userStmt->close();
    } else {
        // ── Insert new user ──
        if (!$password) {
            throw new Exception("Password required for new user.");
        }
        $hashed = password_hash($password,PASSWORD_DEFAULT);
        $role   = 'hr';
        $ustatus = 'active';

        $userSql = "INSERT INTO users (username,password,email,role,employee_id,status) 
                    VALUES (?,?,?,?,?,?)";
        $userStmt = $conn->prepare($userSql);
        $userStmt->bind_param("ssssis",$username,$hashed,$email,$role,$empId,$ustatus);
        $userStmt->execute();
        $userStmt->close();
    }

    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'HR record saved successfully.']);

} catch (Throwable $th) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error: '.$th->getMessage()]);
    exit();
}
