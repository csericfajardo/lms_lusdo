<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once '../config/database.php';

// Require logged-in employee
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employee') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $user_id = (int) $_SESSION['user_id'];

    $sql = "
        SELECT 
            e.employee_number AS employee_no,
            CONCAT(e.first_name, ' ', e.last_name) AS name,
            e.email,
            e.position,
            e.employment_type
        FROM employees e
        INNER JOIN users u ON u.employee_id = e.employee_id
        WHERE u.user_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'employee_no' => $row['employee_no'],
            'name' => $row['name'],
            'email' => $row['email'],
            'position' => $row['position'],
            'employment_type' => $row['employment_type']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Employee details not found']);
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
