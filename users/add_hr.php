<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// 1) Auth guard – only logged-in admins may add HR
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin access required.']);
    exit();
}

// 2) Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

// 3) Read & validate inputs (matches your modal: name="username|email|password")
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
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

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit();
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// 4) Duplicate checks (username + email)
try {
    // Username unique (your DB has UNIQUE on users.username)
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists.']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // Email check (optional but recommended)
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email is already in use.']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // 5) Insert HR user
    $role = 'hr';
    $status = 'active';
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $email, $hash, $role, $status);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'HR added successfully.']);
    } else {
        // Likely constraint error or DB issue
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add HR. Please try again.']);
    }
    $stmt->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
    // You can log $e->getMessage() to a server log if you maintain one.
    // error_log('add_hr.php error: ' . $e->getMessage());
}

$conn->close();
