<?php
require_once '../config/database.php';

$sql = "SELECT * FROM users WHERE role = 'hr'";
$result = $conn->query($sql);
?>

<table class="table table-bordered table-striped table-hover">
    <thead class="thead-dark">
        <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($row['username']); ?></td>
            <td><?php echo htmlspecialchars($row['email']); ?></td>
            <td><?php echo htmlspecialchars($row['status']); ?></td>
            <td>
                <button class="btn btn-sm btn-warning edit-hr-btn"
                    data-id="<?php echo $row['user_id']; ?>"
                    data-username="<?php echo htmlspecialchars($row['username']); ?>"
                    data-email="<?php echo htmlspecialchars($row['email']); ?>">
                    Edit
                </button>
                <button class="btn btn-sm btn-danger delete-hr-btn"
                    data-id="<?php echo $row['user_id']; ?>">
                    Delete
                </button>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>
