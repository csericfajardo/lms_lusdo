<?php
session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login    = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $error = "Please enter your username/email and password.";
    } else {
        // Look up by username OR email. (No WHERE status='active' here; we’ll check after fetch.)
        $sql = "
            SELECT
                u.user_id, u.username, u.password, u.email, u.role, u.employee_id, u.status,
                e.employee_number, e.first_name, e.middle_name, e.last_name
            FROM users u
            LEFT JOIN employees e ON e.employee_id = u.employee_id
            WHERE (u.username = ? OR u.email = ?)
            LIMIT 1
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $login, $login);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$user) {
                $error = "Invalid credentials.";
            } elseif (strcasecmp($user['status'] ?? 'inactive', 'active') !== 0) {
                $error = "Your account is inactive. Please contact the administrator.";
            } elseif (!password_verify($password, $user['password'])) {
                $error = "Invalid credentials.";
            } else {
                // Build friendly name
                $first  = $user['first_name']  ?? '';
                $middle = $user['middle_name'] ?? '';
                $last   = $user['last_name']   ?? '';
                $mi     = $middle !== '' ? ' ' . strtoupper(substr($middle, 0, 1)) . '.' : '';
                $fullName = '';
                if ($first !== '' || $last !== '') {
                    $fullName = ucwords(strtolower($first)) . $mi . ' ' . ucwords(strtolower($last));
                    $fullName = trim($fullName);
                }

                // Set session variables (with backward-compat keys)
                $_SESSION['user_id']          = (int)$user['user_id'];
                $_SESSION['username']         = $user['username'];
                $_SESSION['role']             = ($user['role'] ?? '') !== '' ? $user['role'] : 'employee'; // fallback
                $_SESSION['email']            = $user['email'] ?? '';

                $_SESSION['employee_id']      = $user['employee_id'] ? (int)$user['employee_id'] : null;
                $_SESSION['employee_number']  = $user['employee_number'] ?? '';
                $_SESSION['employee_name']    = $fullName;

                // Backward compatibility (some pages may still read these)
                $_SESSION['name']             = $fullName;                          // old key
                $_SESSION['employee_no']      = $user['employee_number'] ?? '';     // old key

                // Keep your existing alert + Refresh-style redirects (works with output buffering)
                echo "<script>
                    alert('Login successful for ".htmlspecialchars($user['username'], ENT_QUOTES)." (role: ".htmlspecialchars($_SESSION['role'], ENT_QUOTES).")');
                </script>";

                if ($_SESSION['role'] === 'super_admin') {
                    header("Refresh: 0; URL=/depedlu_lms/dashboard/superadmin_dashboard.php");
                } elseif ($_SESSION['role'] === 'admin') {
                    header("Refresh: 0; URL=/depedlu_lms/dashboard/admin_dashboard.php");
                } elseif ($_SESSION['role'] === 'hr') {
                    header("Refresh: 0; URL=/depedlu_lms/dashboard/hr_dashboard.php");
                } else {
                    header("Refresh: 0; URL=/depedlu_lms/dashboard/employee_dashboard.php");
                }
                exit();
            }
        } else {
            $error = "Server error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>DepEd La Union LMS - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Keep your original CSS path which works in your setup -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/depedlu_lms/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body class="login-page">
<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height:100vh;">
        <div class="col-md-6">
            <div class="card shadow p-4">
                <h2 class="text-center mb-4">DepEd La Union LMS</h2>

                <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
