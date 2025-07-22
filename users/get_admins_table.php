<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$sql = "SELECT * FROM users WHERE role = 'admin'";
$result = $conn->query($sql);
?>

<div class="table-responsive">
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
    <button class="btn btn-sm btn-warning edit-btn"
    data-id="<?php echo $row['user_id']; ?>"
    data-username="<?php echo htmlspecialchars($row['username']); ?>"
    data-email="<?php echo htmlspecialchars($row['email']); ?>">
    Edit
</button>
    <button class="btn btn-sm btn-danger delete-btn"
    data-id="<?php echo $row['user_id']; ?>">
    Delete
</button>

</td>

                 
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
