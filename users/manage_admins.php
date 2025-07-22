<?php
session_start();
require_once '../config/database.php';

// Check if super admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: /depedlu_lms/auth/login.php");
    exit();
}

// Fetch all admin users
$sql = "SELECT * FROM users WHERE role = 'admin'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/depedlu_lms/css/global.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<div class="container mt-5">
    <h2 class="text-center text-danger">Manage Admin Accounts</h2>
    
    <button class="btn btn-primary mb-3" onclick="location.href='add_admin.php'">Add New Admin</button>

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
                        <a href="edit_admin.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="delete_admin.php?id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <a href="/depedlu_lms/dashboard/superadmin_dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</div>
</div>

</body>
</html>
