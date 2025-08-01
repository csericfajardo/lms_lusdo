<?php
require_once __DIR__ . '/../config/database.php';

$statusFilter = $_GET['status'] ?? '';

// Base query
$sql = "
  SELECT 
    la.application_id, 
    e.employee_number, 
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    CASE
      WHEN la.leave_type_id = 12 THEN
        CONCAT(lt.name, ' - ', COALESCE(ce.source, ''))
      ELSE
        lt.name
    END AS leave_type,
    la.number_of_days, 
    la.status, 
    la.created_at
  FROM leave_applications la
  JOIN employees e ON la.employee_id = e.employee_id
  JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
  LEFT JOIN leave_application_details d
    ON la.application_id = d.application_id
   AND d.field_name = 'cto_id'
  LEFT JOIN cto_earnings ce
    ON ce.cto_id = CAST(d.field_value AS UNSIGNED)
";

// Add status filter if needed
if (!empty($statusFilter)) {
    $sql .= " WHERE la.status = ?";
    $stmt = $conn->prepare($sql . " ORDER BY la.created_at DESC");
    $stmt->bind_param("s", $statusFilter);
} else {
    $stmt = $conn->prepare($sql . " ORDER BY la.created_at DESC");
}

$stmt->execute();
$result = $stmt->get_result();

echo '<table class="table table-bordered table-striped">';
echo '<thead>
        <tr>
          <th>Application ID</th>
          <th>Employee</th>
          <th>Leave Type</th>
          <th>Days</th>
          <th>Status</th>
          <th>Filed Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>';

while ($row = $result->fetch_assoc()) {
    $applicationId   = (int)$row['application_id'];
    $employeeDisplay = htmlspecialchars("{$row['employee_number']} - {$row['employee_name']}");
    $leaveType       = htmlspecialchars($row['leave_type']);
    $days            = (float)$row['number_of_days'];
    $status          = htmlspecialchars($row['status']);
    // Show both date + time
    $filedDate       = date("M j, Y g:ia", strtotime($row['created_at']));
    $statusClass     = strtolower($row['status']);

    echo "<tr>
            <td>{$applicationId}</td>
            <td>{$employeeDisplay}</td>
            <td>{$leaveType}</td>
            <td>{$days}</td>
            <td class='status-cell {$statusClass}' data-application-id='{$applicationId}'>{$status}</td>
            <td>{$filedDate}</td>
            <td>
              <button 
                type='button' 
                class='btn btn-sm btn-primary view-application-btn' 
                data-application-id='{$applicationId}'>
                View
              </button>
            </td>
          </tr>";
}

echo '</tbody></table>';

$stmt->close();
?>
