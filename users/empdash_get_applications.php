<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
header('Content-Type: text/html'); // returns HTML table rows

// Require logged-in employee
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employee') {
    http_response_code(403);
    exit('Unauthorized');
}

$user_id = (int) $_SESSION['user_id'];

// Get the employee_id for this user
$stmtEmp = $conn->prepare("SELECT employee_id FROM users WHERE user_id = ?");
$stmtEmp->bind_param("i", $user_id);
$stmtEmp->execute();
$empRow = $stmtEmp->get_result()->fetch_assoc();
$stmtEmp->close();

if (!$empRow) {
    exit('No applications found');
}
$employee_id = (int) $empRow['employee_id'];

// Fetch leave applications for this employee
$sql = "
    SELECT 
        la.application_id,
        lt.name AS leave_type,
        CONCAT(
            DATE_FORMAT(MIN(lad.field_value), '%Y-%m-%d'), ' to ',
            DATE_FORMAT(MAX(lad.field_value), '%Y-%m-%d')
        ) AS date_range,
        la.status
    FROM leave_applications la
    INNER JOIN leave_types lt ON lt.leave_type_id = la.leave_type_id
    LEFT JOIN leave_application_details lad 
        ON lad.application_id = la.application_id 
        AND lad.field_name IN ('date_from','date_to')
    WHERE la.employee_id = ?
    GROUP BY la.application_id, lt.name, la.status
    ORDER BY la.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

// Output as HTML rows
while ($row = $result->fetch_assoc()) {
    $ref = htmlspecialchars($row['application_id']);
    $type = htmlspecialchars($row['leave_type']);
    $dates = htmlspecialchars($row['date_range']);
    $status = htmlspecialchars($row['status']);
    echo "<tr>
            <td>{$ref}</td>
            <td>{$type}</td>
            <td>{$dates}</td>
            <td>{$status}</td>
            <td><button class='btn btn-link view-app' data-ref='{$ref}'>View</button></td>
          </tr>";
}

$stmt->close();
