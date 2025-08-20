<?php
session_start();
require_once '../config/database.php';

/* -----------------------------
   Access control
------------------------------*/
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

/* Load user row (to get role + employee link) */
$sqlUser = "SELECT user_id, role, employee_id, username FROM users WHERE user_id = ? LIMIT 1";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
$userRow = $resUser->fetch_assoc();
$stmtUser->close();

if (!$userRow) {
    header('Location: ../auth/login.php');
    exit();
}

/* Allow explicit 'employee' role OR any user that has an employee_id link */
if ($userRow['role'] !== 'employee' && empty($userRow['employee_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

/* Prefer employee number from session; fall back via users.employee_id */
$employeeNo = $_SESSION['employee_no'] ?? null;
$emp = null;

if (!empty($employeeNo)) {
    $sqlEmp = "SELECT * FROM employees WHERE employee_number = ? LIMIT 1";
    $stmtEmp = $conn->prepare($sqlEmp);
    $stmtEmp->bind_param("s", $employeeNo);
    $stmtEmp->execute();
    $resEmp = $stmtEmp->get_result();
    $emp = $resEmp->fetch_assoc();
    $stmtEmp->close();
}

if (!$emp) {
    $employeeIdFromUser = (int)($userRow['employee_id'] ?? 0);
    if ($employeeIdFromUser <= 0) {
        echo "<div class='alert alert-danger m-4'>Your account is not linked to an employee record. Please contact HR.</div>";
        exit();
    }
    $sqlEmp = "SELECT * FROM employees WHERE employee_id = ? LIMIT 1";
    $stmtEmp = $conn->prepare($sqlEmp);
    $stmtEmp->bind_param("i", $employeeIdFromUser);
    $stmtEmp->execute();
    $resEmp = $stmtEmp->get_result();
    $emp = $resEmp->fetch_assoc();
    $stmtEmp->close();

    if (!$emp) {
        echo "<div class='alert alert-danger m-4'>Employee record not found. Please contact HR.</div>";
        exit();
    }

    /* Cache employee number into session for next requests */
    if (!empty($emp['employee_number'])) {
        $_SESSION['employee_no'] = $emp['employee_number'];
        $employeeNo = $emp['employee_number'];
    }
}

$employeeId = (int)$emp['employee_id'];

/* -----------------------------
   Display helpers
------------------------------*/
$first  = isset($emp['first_name'])  ? ucwords(strtolower($emp['first_name'])) : '';
$middle = !empty($emp['middle_name']) ? ' ' . strtoupper(substr($emp['middle_name'], 0, 1)) . '.' : '';
$last   = isset($emp['last_name'])   ? ucwords(strtolower($emp['last_name']))  : '';
$fullName = trim($first . $middle . ' ' . $last);

/* -----------------------------
   Leave credits (exclude CTO=12), only positive balances
------------------------------*/
$sqlCredits = "
  SELECT lc.leave_type_id, lt.name AS leave_type, lc.balance_credits, lc.updated_at
  FROM leave_credits lc
  JOIN leave_types lt ON lt.leave_type_id = lc.leave_type_id
  WHERE lc.employee_id = ? 
    AND lc.leave_type_id <> 12
    AND lc.balance_credits > 0
  ORDER BY 
    CASE 
      WHEN lc.leave_type_id = 1 THEN 0
      WHEN lc.leave_type_id = 2 THEN 1
      ELSE 2
    END, lt.name
";
$stmt = $conn->prepare($sqlCredits);
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$creditsResult = $stmt->get_result();
$leaveCredits = $creditsResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* -----------------------------
   CTO earnings (positive balance)
------------------------------*/
$sqlCTO = "
  SELECT cto_id, days_earned, days_used, (days_earned - days_used) AS balance, earned_at, expires_at, source
  FROM cto_earnings
  WHERE employee_id = ? AND (days_earned - days_used) > 0
  ORDER BY earned_at DESC
";
$stmt = $conn->prepare($sqlCTO);
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$ctoResult = $stmt->get_result();
$ctoCredits = $ctoResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Prepackage CTO minimal data for client */
$clientCto = array_map(function($r){
    return [
        'cto_id'    => (int)$r['cto_id'],
        'source'    => $r['source'],
        'balance'   => (float)$r['balance'],
        'earned_at' => $r['earned_at'],
        'expires_at'=> $r['expires_at'],
    ];
}, $ctoCredits);

/* -----------------------------
   Applications list for this employee (with approver info & dates)
------------------------------*/
$sqlApps = "
  SELECT 
    la.application_id,
    la.leave_type_id,
    lt.name AS leave_type,
    la.number_of_days,
    la.status,
    la.created_at,
    la.updated_at,
    la.approved_by,
    u.username AS approver_username,
    u.email    AS approver_email,
    e.first_name AS appr_first,
    e.middle_name AS appr_middle,
    e.last_name AS appr_last,
    df.field_value AS date_from,
    dt.field_value AS date_to,
    de.field_value AS effective_date
  FROM leave_applications la
  JOIN leave_types lt ON lt.leave_type_id = la.leave_type_id
  LEFT JOIN leave_application_details df 
         ON df.application_id = la.application_id AND df.field_name = 'date_from'
  LEFT JOIN leave_application_details dt 
         ON dt.application_id = la.application_id AND dt.field_name = 'date_to'
  LEFT JOIN leave_application_details de 
         ON de.application_id = la.application_id AND de.field_name = 'effective_date'
  LEFT JOIN users u ON u.user_id = la.approved_by
  LEFT JOIN employees e ON e.employee_id = u.employee_id
  WHERE la.employee_id = ?
  ORDER BY la.created_at DESC
";
$stmtApps = $conn->prepare($sqlApps);
$stmtApps->bind_param("i", $employeeId);
$stmtApps->execute();
$resApps = $stmtApps->get_result();
$applications = $resApps->fetch_all(MYSQLI_ASSOC);
$stmtApps->close();

/* Display helpers for the table */
function approverDisplayName(array $r): string {
    if (empty($r['approved_by'])) return '—';
    $first  = $r['appr_first']  ?? '';
    $mid    = $r['appr_middle'] ?? '';
    $last   = $r['appr_last']   ?? '';
    if ($first || $last) {
        $first = ucwords(strtolower($first));
        $mi    = $mid ? ' ' . strtoupper(substr($mid, 0, 1)) . '.' : '';
        $last  = ucwords(strtolower($last));
        return trim($first . $mi . ' ' . $last);
    }
    if (!empty($r['approver_username'])) return $r['approver_username'];
    if (!empty($r['approver_email']))    return $r['approver_email'];
    return '—';
}
function appDateRange(array $r): string {
    $from = $r['date_from'] ?? '';
    $to   = $r['date_to']   ?? '';
    $eff  = $r['effective_date'] ?? '';
    $fmt  = function($d){ return $d ? date('M d, Y', strtotime($d)) : ''; };

    if ($from) {
        return $to ? ($fmt($from) . ' – ' . $fmt($to)) : $fmt($from);
    }
    if ($eff) return $fmt($eff);
    return '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Employee Dashboard - DepEd LU LMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="../css/employee_dashboard.css"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
</head>
<body>

  <!-- Header -->
  <header class="dashboard-header d-flex justify-content-between align-items-center px-3 py-2">
    <h3 class="m-0">DepEd LU LMS</h3>
    <div>
      <button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#personalModal">👤</button>
      <a href="../auth/logout.php" class="btn btn-primary btn-sm ml-2">Logout</a>
    </div>
  </header>

  <!-- Line: Employee Number | Name -->
  <div class="container-fluid mt-3">
    <p class="employee-line mb-3">
      <strong><?= htmlspecialchars($emp['employee_number']) ?></strong> | <?= htmlspecialchars($fullName) ?>
    </p>

    <!-- Leave Credits -->
    <h6 class="section-title">Leave Credits</h6>
    <div class="leave-credits-container">
      <?php if (empty($leaveCredits)): ?>
        <p class="text-muted">No available leave credits.</p>
      <?php else: ?>
        <?php foreach ($leaveCredits as $credit): ?>
          <div class="leave-credit-box clickable"
               data-leave-type-id="<?= (int)$credit['leave_type_id'] ?>"
               data-leave-type-name="<?= htmlspecialchars($credit['leave_type']) ?>"
               data-employee-id="<?= (int)$employeeId ?>">
            <h6 class="mb-1"><?= htmlspecialchars($credit['leave_type']) ?></h6>
            <div class="box-balance"><span>Balance</span> <?= number_format((float)$credit['balance_credits'], 2) ?> day(s)</div>
            <div class="box-meta">Updated <?= htmlspecialchars(date('M d, Y', strtotime($credit['updated_at']))) ?></div>
            <button type="button" class="btn btn-primary btn-sm mt-2 apply-btn">Apply</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- CTO -->
    <h6 class="section-title mt-4">Compensatory Time-Off (CTO)</h6>
    <div class="leave-credits-container">
      <?php if (empty($ctoCredits)): ?>
        <p class="text-muted">No available CTOs.</p>
      <?php else: ?>
        <?php foreach ($ctoCredits as $cto): ?>
          <div class="leave-credit-box clickable"
               data-leave-type-id="12"
               data-leave-type-name="Compensatory Time-Off"
               data-employee-id="<?= (int)$employeeId ?>"
               data-cto-id="<?= (int)$cto['cto_id'] ?>">
            <h6 class="mb-1"><?= htmlspecialchars($cto['source']) ?></h6>
            <div class="box-balance"><span>Balance</span> <?= number_format((float)$cto['balance'], 2) ?> day(s)</div>
            <div class="box-meta">
              Earned <?= htmlspecialchars(date('M d, Y', strtotime($cto['earned_at']))) ?> ·
              Expires <?= htmlspecialchars(date('M d, Y', strtotime($cto['expires_at']))) ?>
            </div>
            <button type="button" class="btn btn-primary btn-sm mt-2 apply-btn">Apply</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Applications table -->
    <h6 class="section-title mt-4">My Leave Applications</h6>
    <div class="table-responsive">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>#</th>
            <th>Type</th>
            <th>Dates</th>
            <th>Days</th>
            <th>Status</th>
            <th>Filed</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody id="applicationsTableBody">
          <?php if (empty($applications)): ?>
            <tr><td colspan="8" class="text-muted text-center">No applications yet.</td></tr>
          <?php else: ?>
            <?php foreach ($applications as $row): ?>
              <tr>
                <td><?= (int)$row['application_id'] ?></td>
                <td><?= htmlspecialchars($row['leave_type']) ?></td>
                <td><?= htmlspecialchars(appDateRange($row)) ?></td>
                <td><?= number_format((float)$row['number_of_days'], 2) ?></td>
                <td>
                  <?php
                    $status = $row['status'];
                    $badge  = 'secondary';
                    if ($status === 'Approved') $badge = 'success';
                    elseif ($status === 'Pending') $badge = 'warning';
                    elseif ($status === 'Rejected') $badge = 'danger';
                    elseif ($status === 'Cancelled') $badge = 'dark';
                  ?>
                  <span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($status) ?></span>
                </td>
     
                <td><?= htmlspecialchars(date('M d, Y', strtotime($row['created_at']))) ?></td>
                <td><?= htmlspecialchars(date('M d, Y', strtotime($row['updated_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Personal Details Modal -->
  <div class="modal fade" id="personalModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content p-3">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title">Personal Details</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <div class="modal-body pt-2">
          <table class="table table-sm mb-0">
            <tr><th>Employee No</th><td><?= htmlspecialchars($emp['employee_number']) ?></td></tr>
            <tr><th>Full Name</th><td><?= htmlspecialchars($fullName) ?></td></tr>
            <tr><th>Email</th><td><?= htmlspecialchars($emp['email']) ?></td></tr>
            <tr><th>Position</th><td><?= htmlspecialchars($emp['position']) ?></td></tr>
            <tr><th>Office</th><td><?= htmlspecialchars($emp['office']) ?></td></tr>
            <tr><th>Date Hired</th><td><?= htmlspecialchars($emp['date_hired']) ?></td></tr>
            <tr><th>Status</th><td><?= htmlspecialchars($emp['status']) ?></td></tr>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Apply Leave Modal (Employee) -->
  <div class="modal fade" id="empApplyLeaveModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document"> <!-- scrollable -->
      <form id="empApplyLeaveForm" enctype="multipart/form-data">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Apply for Leave</h5>
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <!-- Hidden -->
            <input type="hidden" name="employee_id" id="emp_apply_employee_id" value="<?= (int)$employeeId ?>">
            <input type="hidden" name="leave_type_id" id="emp_apply_leave_type_id">

            <!-- Display -->
            <div class="form-group">
              <label>Leave Type</label>
              <input type="text" class="form-control" id="emp_apply_leave_type_name" readonly>
            </div>

            <!-- Dynamic Fields -->
            <div id="empApplyLeaveFields"></div>

            <!-- Status enforced as Pending on server -->
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Submit Application</button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Data for JS -->
  <script>
    window.EMP_CTO = <?= json_encode($clientCto, JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
  <script src="../js/employee_applyleave.js"></script>
</body>
</html>
