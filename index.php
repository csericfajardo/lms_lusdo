<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'super_admin':
            header("Location: /depedlu_lms/dashboard/superadmin_dashboard.php");
            break;
        case 'admin':
            header("Location: /depedlu_lms/dashboard/admin_dashboard.php");
            break;
        case 'hr':
            header("Location: /depedlu_lms/dashboard/hr_dashboard.php");
            break;
        case 'employee':
            header("Location: /depedlu_lms/dashboard/employee_dashboard.php");
            break;
        default:
            session_destroy();
            header("Location: /depedlu_lms/auth/login.php");
    }
    exit();
} else {
    header("Location: /depedlu_lms/auth/login.php");
    exit();
}
?>
