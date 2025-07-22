<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Add your session & role checks here if needed

$sql = "SELECT employee_id, employee_number, first_name, middle_name, last_name, employment_type, position, office, email, date_hired, status FROM employees";
$result = $conn->query($sql);
?>

<div class="table-responsive">
<table class="table table-bordered table-striped table-hover">
  <thead class="thead-dark">
    <tr>
      <th>Employee Number</th>
      <th>Full Name</th>
      <th>Employment Type</th>
      <th>Position</th>
      <th>Office</th>
      <th>Email</th>
      <th>Date Hired</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php while ($row = $result->fetch_assoc()) { 
      $full_name = $row['first_name'] . ' ' . (!empty($row['middle_name']) ? $row['middle_name'] . ' ' : '') . $row['last_name'];
  ?>
    <tr>
      <td><?php echo htmlspecialchars($row['employee_number']); ?></td>
      <td><?php echo htmlspecialchars($full_name); ?></td>
      <td><?php echo htmlspecialchars($row['employment_type']); ?></td>
      <td><?php echo htmlspecialchars($row['position']); ?></td>
      <td><?php echo htmlspecialchars($row['office']); ?></td>
      <td><?php echo htmlspecialchars($row['email']); ?></td>
      <td><?php echo htmlspecialchars($row['date_hired']); ?></td>
      <td><?php echo htmlspecialchars($row['status']); ?></td>
      <td>

  <button class="btn btn-info btn-sm view-employee-btn" data-id="<?php echo $row['employee_id']; ?>">View</button>


      </td>
    </tr>
  <?php } ?>
  </tbody>
</table>
</div>
