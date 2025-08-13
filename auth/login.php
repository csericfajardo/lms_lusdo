<?php
session_start();
require_once '../config/database.php';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login    = trim($_POST['username']); // can be username OR email
    $password = trim($_POST['password']);

    // Lookup by username OR email (ensure users.email is UNIQUE)
    $sql = "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $error = "Server error. Please try again.";
    } else {
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
    // Set session variables
    $_SESSION['user_id']  = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];

    // Optional extras for dashboards:
    $_SESSION['name']        = $user['name'] ?? '';
    $_SESSION['employee_no'] = $user['employee_no'] ?? '';
    $_SESSION['email']       = $user['email'] ?? '';

    // Debug alert before redirect
    echo "<script>
        alert('Login successful for {$user['username']} (role: {$user['role']})');
    </script>";

    // Redirect based on role
    if ($user['role'] == 'super_admin') {
        header("Refresh: 0; URL=/depedlu_lms/dashboard/superadmin_dashboard.php");
    } else if ($user['role'] == 'admin') {
        header("Refresh: 0; URL=/depedlu_lms/dashboard/admin_dashboard.php");
    } else if ($user['role'] == 'hr') {
        header("Refresh: 0; URL=/depedlu_lms/dashboard/hr_dashboard.php");
    } else {
        header("Refresh: 0; URL=/depedlu_lms/dashboard/employee_dashboard.php");
    }
    exit();
} else {
    $error = "Invalid password.";
}
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>DepEd La Union LMS - Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/depedlu_lms/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height:100vh;">
        <div class="col-md-6">
            <div class="card shadow p-4">
                <h2 class="text-center text-danger mb-4">DepEd La Union LMS Login.</h2>

                <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input type="text" class="form-control" name="username" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
