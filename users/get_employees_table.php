<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Search filter
$search = trim($_GET['q'] ?? '');

// Base query
$sql = "SELECT employee_id, employee_number, first_name, middle_name, last_name, employment_type, position, office, email, date_hired, status 
        FROM employees";

// If searching, add WHERE conditions
if ($search !== '') {
    $like = "%" . $conn->real_escape_string($search) . "%";
    $sql .= " WHERE 
                employee_number LIKE '$like' 
                OR first_name LIKE '$like' 
                OR middle_name LIKE '$like' 
                OR last_name LIKE '$like' 
                OR office LIKE '$like'";
}

$sql .= " ORDER BY employee_number ASC";

$result = $conn->query($sql);
?>



<!-- Table -->
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
      <td><?= htmlspecialchars($row['employee_number']); ?></td>
      <td><?= htmlspecialchars($full_name); ?></td>
      <td><?= htmlspecialchars($row['employment_type']); ?></td>
      <td><?= htmlspecialchars($row['position']); ?></td>
      <td><?= htmlspecialchars($row['office']); ?></td>
      <td><?= htmlspecialchars($row['email']); ?></td>
      <td><?= htmlspecialchars($row['date_hired']); ?></td>
      <td><?= htmlspecialchars($row['status']); ?></td>
      <td>
        <button class="btn btn-info btn-sm view-employee-btn" data-id="<?= $row['employee_id']; ?>">View</button>
      </td>
    </tr>
  <?php } ?>
  </tbody>
</table>
</div>

<script>
$(document).ready(function(){
    $('#employeeSearch').on('keyup', function(){
        var search = $(this).val();
        $.get('get_employees_table.php', { search: search }, function(data){
            $('#employeesTableContainer').html(data);
        });
    });
});
</script>
