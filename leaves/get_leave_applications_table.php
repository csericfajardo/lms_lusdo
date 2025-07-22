<?php
require_once '../config/database.php';

$status = $_GET['status'] ?? '';

$sql = "SELECT la.application_id, la.employee_id, e.first_name, e.middle_name, e.last_name, lt.name AS leave_type, 
        la.start_date, la.end_date, la.number_of_days, la.reason, la.status, la.filed_by, la.approved_by, la.created_at
        FROM leave_applications la
        JOIN employees e ON la.employee_id = e.employee_id
        JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id";

if ($status && in_array($status, ['Pending', 'Approved', 'Rejected', 'Cancelled'])) {
    $sql .= " WHERE la.status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $status);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<table class="table table-bordered table-striped">
    <thead class="thead-dark">
        <tr>
            <th>Employee</th>
            <th>Leave Type</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Days</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Filed By</th>
            <th>Approved By</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()):
            $full_name = $row['first_name'] . ' ' . (!empty($row['middle_name']) ? $row['middle_name'] . ' ' : '') . $row['last_name'];
        ?>
        <tr>
            <td><?php echo htmlspecialchars($full_name); ?></td>
            <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
            <td><?php echo htmlspecialchars($row['start_date']); ?></td>
            <td><?php echo htmlspecialchars($row['end_date']); ?></td>
            <td><?php echo htmlspecialchars($row['number_of_days']); ?></td>
            <td><?php echo htmlspecialchars($row['reason']); ?></td>
            <td><?php echo htmlspecialchars($row['status']); ?></td>
            <td><?php echo htmlspecialchars($row['filed_by']); ?></td>
            <td><?php echo htmlspecialchars($row['approved_by'] ?? '-'); ?></td>
            <td>
                <?php if ($row['status'] === 'Pending'): ?>
                    <button class="btn btn-success btn-sm approve-btn" data-id="<?php echo $row['application_id']; ?>">Approve</button>
                    <button class="btn btn-danger btn-sm reject-btn" data-id="<?php echo $row['application_id']; ?>">Reject</button>
                <?php endif; ?>
                <button class="btn btn-secondary btn-sm view-btn" data-id="<?php echo $row['application_id']; ?>">View</button>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
