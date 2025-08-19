<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

header('Content-Type: application/json');

// Auth: allow admin or super_admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Method guard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// ── Read & sanitize inputs ───────────────────────────────────────────────
$hr_id    = isset($_POST['edit_hr_id']) ? (int)$_POST['edit_hr_id'] : 0;
$username = trim($_POST['edit_hr_username'] ?? '');
$email    = trim($_POST['edit_hr_email'] ?? '');
$password = trim($_POST['edit_hr_password'] ?? '');
$status   = isset($_POST['edit_hr_status']) ? trim($_POST['edit_hr_status']) : null;

// Employee profile fields
$employee_number = trim($_POST['employee_number'] ?? '');
$first_name      = trim($_POST['first_name'] ?? '');
$middle_name     = trim($_POST['middle_name'] ?? '');
$last_name       = trim($_POST['last_name'] ?? '');
$position        = trim($_POST['position'] ?? '');
$office          = trim($_POST['office'] ?? '');
$employment_type = trim($_POST['employment_type'] ?? '');
$date_hired      = trim($_POST['date_hired'] ?? '');

// ── Basic validation ─────────────────────────────────────────────────────
if ($hr_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid HR id.']);
    exit();
}
if ($username === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'Username and email are required.']);
    exit();
}
if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username must be 3–50 chars (letters, numbers, . _ -).']);
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit();
}
if ($status !== null && !in_array($status, ['active', 'inactive'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit();
}

// ── Ensure the target user exists and is HR ──────────────────────────────
$chkSql = "SELECT user_id, employee_id FROM users WHERE user_id = ? AND role = 'hr' LIMIT 1";
$stmt = $conn->prepare($chkSql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (prepare check).']);
    exit();
}
$stmt->bind_param('i', $hr_id);
$stmt->execute();
$res = $stmt->get_result();
$userRow = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$userRow) {
    echo json_encode(['success' => false, 'message' => 'HR account not found.']);
    exit();
}
$employee_id = (int)($userRow['employee_id'] ?? 0);

// ── Uniqueness checks ────────────────────────────────────────────────────
try {
    // username unique
    $uSql = "SELECT 1 FROM users WHERE username = ? AND user_id <> ? LIMIT 1";
    $u = $conn->prepare($uSql);
    $u->bind_param('si', $username, $hr_id);
    $u->execute();
    $u->store_result();
    if ($u->num_rows > 0) {
        $u->close();
        echo json_encode(['success' => false, 'message' => 'Username already in use.']);
        exit();
    }
    $u->close();

    // email unique
    $eSql = "SELECT 1 FROM users WHERE email = ? AND user_id <> ? LIMIT 1";
    $e = $conn->prepare($eSql);
    $e->bind_param('si', $email, $hr_id);
    $e->execute();
    $e->store_result();
    if ($e->num_rows > 0) {
        $e->close();
        echo json_encode(['success' => false, 'message' => 'Email already in use.']);
        exit();
    }
    $e->close();
} catch (Throwable $th) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (uniqueness).']);
    exit();
}

// ── Update USERS table ───────────────────────────────────────────────────
$fields = ['username = ?', 'email = ?'];
$params = [$username, $email];
$types  = 'ss';

if ($status !== null) {
    $fields[] = 'status = ?';
    $params[] = $status;
    $types   .= 's';
}
if ($password !== '') {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $fields[] = 'password = ?';
    $params[] = $hashed;
    $types   .= 's';
}

$fieldsSql = implode(', ', $fields);
$updateSql = "UPDATE users SET {$fieldsSql} WHERE user_id = ? AND role = 'hr'";
$params[]  = $hr_id;
$types    .= 'i';

$stmt = $conn->prepare($updateSql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error (prepare user update).']);
    exit();
}
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update HR login account.']);
    exit();
}
$stmt->close();

// ── Update EMPLOYEES table if linked ─────────────────────────────────────
if ($employee_id > 0) {
    $empSql = "
      UPDATE employees
      SET employee_number=?, first_name=?, middle_name=?, last_name=?, 
          position=?, office=?, employment_type=?, date_hired=?
      WHERE employee_id = ?
    ";
    $empStmt = $conn->prepare($empSql);
    if ($empStmt) {
        $empStmt->bind_param(
            "ssssssssi",
            $employee_number, $first_name, $middle_name, $last_name,
            $position, $office, $employment_type, $date_hired,
            $employee_id
        );
        $empStmt->execute();
        $empStmt->close();
    }
}

// ── Success ──────────────────────────────────────────────────────────────
echo json_encode(['success' => true, 'message' => 'HR updated successfully.']);
