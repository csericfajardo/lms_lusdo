<?php
session_start();
require_once '../config/database.php';

// Access control: only employees
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../auth/login.php");
    exit();
}

// Get employee details from logged-in user
$sqlEmp = "
    SELECT e.*
    FROM employees e
    JOIN users u ON e.employee_id = u.employee_id
    WHERE u.user_id = ?
    LIMIT 1
";
$stmtEmp = $conn->prepare($sqlEmp);
$stmtEmp->bind_param("i", $_SESSION['user_id']);
$stmtEmp->execute();
$resultEmp = $stmtEmp->get_result();

if ($resultEmp->num_rows === 0) {
    echo "Employee details not found.";
    exit();
}
$emp = $resultEmp->fetch_assoc();
$stmtEmp->close();

// Build full name
$first   = ucwords(strtolower($emp['first_name']));
$middle  = $emp['middle_name']
           ? ' ' . strtoupper(substr($emp['middle_name'],0,1)) . '.'
           : '';
$last    = ucwords(strtolower($emp['last_name']));
$fullName = "$first$middle $last";

// Fetch leave credits (exclude CTO = id 12)
$sqlCredits = "
  SELECT 
    lc.leave_type_id,
    lt.name AS leave_type_name,
    lc.total_credits,
    lc.used_credits,
    lc.balance_credits,
    lc.updated_at
  FROM leave_credits lc
  JOIN leave_types lt ON lc.leave_type_id = lt.leave_type_id
  WHERE lc.employee_id = ?
    AND lc.leave_type_id <> 12
  ORDER BY 
    CASE 
      WHEN lc.leave_type_id = 1 THEN 0  -- Vacation
      WHEN lc.leave_type_id = 2 THEN 1  -- Sick
      ELSE 2
    END,
    lt.name
";
$stmtCredits = $conn->prepare($sqlCredits);
$stmtCredits->bind_param("i", $emp['employee_id']);
$stmtCredits->execute();
$resultCredits = $stmtCredits->get_result();
$leaveCredits = $resultCredits->fetch_all(MYSQLI_ASSOC);
$stmtCredits->close();

// Fetch CTO earnings
$sqlCto = "
  SELECT
    cto_id,
    days_earned,
    days_used,
    balance,
    earned_at,
    expires_at,
    source
  FROM cto_earnings
  WHERE employee_id = ?
  ORDER BY earned_at DESC
";
$stmtCto = $conn->prepare($sqlCto);
$stmtCto->bind_param("i", $emp['employee_id']);
$stmtCto->execute();
$resultCto = $stmtCto->get_result();
$ctoRows = $resultCto->fetch_all(MYSQLI_ASSOC);
$stmtCto->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard - DepEd La Union LMS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Roboto', sans-serif; }
        .dashboard-card { border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,.1); margin-bottom: 20px; }
        .dashboard-header { background: #007bff; color: #fff; padding: 20px; border-radius: 12px 12px 0 0; }
        .credit-box, .cto-box {
            border: 1px solid #ddd; border-radius: 10px; padding: 15px; background: #fff;
            width: 250px; margin: 10px; box-shadow: 0 2px 5px rgba(0,0,0,.05);
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card dashboard-card">
        <div class="dashboard-header">
            <h3>Welcome, <?= htmlspecialchars($fullName) ?>!</h3>
            <p>Employee Dashboard</p>
        </div>
        <div class="card-body">
            <h5 class="text-primary mb-3">Personal Details</h5>
            <table class="table table-bordered">
                <tr><th>Employee Number</th><td><?= htmlspecialchars($emp['employee_number']); ?></td></tr>
                <tr><th>Employment Type</th><td><?= htmlspecialchars($emp['employment_type']); ?></td></tr>
                <tr><th>Position</th><td><?= htmlspecialchars($emp['position']); ?></td></tr>
                <tr><th>Office</th><td><?= htmlspecialchars($emp['office']); ?></td></tr>
                <tr><th>Email</th><td><?= htmlspecialchars($emp['email']); ?></td></tr>
                <tr><th>Date Hired</th><td><?= htmlspecialchars($emp['date_hired']); ?></td></tr>
                <tr><th>Status</th><td><?= htmlspecialchars($emp['status']); ?></td></tr>
            </table>

            <h5 class="text-primary mt-4">Leave Credits</h5>
            <div class="d-flex flex-wrap">
                <?php if (empty($leaveCredits)): ?>
                    <p>No leave credits available.</p>
                <?php else: ?>
                    <?php foreach ($leaveCredits as $credit): ?>
                        <div class="credit-box">
                            <h6><?= htmlspecialchars($credit['leave_type_name']); ?></h6>
                            <p><strong>Total:</strong> <?= number_format($credit['total_credits'], 2); ?></p>
                            <p><strong>Used:</strong> <?= number_format($credit['used_credits'], 2); ?></p>
                            <p><strong>Balance:</strong> <?= number_format($credit['balance_credits'], 2); ?></p>
                            <small class="text-muted">Updated: <?= date('M d, Y', strtotime($credit['updated_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h5 class="text-primary mt-4">Compensatory Time-Off (CTO)</h5>
            <div class="d-flex flex-wrap">
                <?php if (empty($ctoRows)): ?>
                    <p>No CTO records available.</p>
                <?php else: ?>
                    <?php foreach ($ctoRows as $cto): ?>
                        <div class="cto-box">
                            <h6><?= htmlspecialchars($cto['source']); ?></h6>
                            <p><strong>Earned:</strong> <?= number_format($cto['days_earned'], 2); ?> days</p>
                            <p><strong>Used:</strong> <?= number_format($cto['days_used'], 2); ?> days</p>
                            <p><strong>Balance:</strong> <?= number_format($cto['balance'], 2); ?> days</p>
                            <small class="text-muted">
                                Earned: <?= date('M d, Y', strtotime($cto['earned_at'])); ?><br>
                                Expires: <?= date('M d, Y', strtotime($cto['expires_at'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <a href="../auth/logout.php" class="btn btn-danger mt-4">Logout</a>
        </div>
    </div>
</div>
</body>
</html>
