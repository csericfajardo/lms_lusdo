<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Access control: Only HR or allowed roles can fetch this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo 'Unauthorized';
    exit();
}

$employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
if ($employee_id <= 0) {
    http_response_code(400);
    echo 'Invalid employee ID.';
    exit();
}

// Fetch employee personal details
$sqlEmp = "SELECT employee_id, employee_number, first_name, middle_name, last_name, employment_type, position, office, email, date_hired, status FROM employees WHERE employee_id = ?";
$stmtEmp = $conn->prepare($sqlEmp);
if (!$stmtEmp) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmtEmp->bind_param("i", $employee_id);
$stmtEmp->execute();
$resultEmp = $stmtEmp->get_result();
if ($resultEmp->num_rows === 0) {
    echo 'Employee not found.';
    exit();
}
$emp = $resultEmp->fetch_assoc();
$stmtEmp->close();

// Fetch leave credits for the employee
$sqlCredits = "
    SELECT 
        lc.credit_id, 
        lc.leave_type_id,
        lt.name AS leave_type_name, 
        lc.balance_credits, 
        lc.updated_at
    FROM leave_credits lc
    JOIN leave_types lt ON lc.leave_type_id = lt.leave_type_id
    WHERE lc.employee_id = ?
";
$stmtCredits = $conn->prepare($sqlCredits);
if (!$stmtCredits) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmtCredits->bind_param("i", $employee_id);
$stmtCredits->execute();
$resultCredits = $stmtCredits->get_result();
$leaveCredits = [];
while ($row = $resultCredits->fetch_assoc()) {
    $leaveCredits[] = $row;
}
$stmtCredits->close();
?>

<!-- Employee Personal Details -->
<div class="employee-details d-flex justify-content-between align-items-start" style="padding: 20px; border: 1px solid #ddd; margin-bottom: 20px; border-radius: 8px; background-color: #fff;">
    <div class="personal-info" style="max-width: 75%;">
        <h4>Personal Details</h4>
        <p><strong>Employee Number:</strong> <?= htmlspecialchars($emp['employee_number']) ?></p>
        <p><strong>Name:</strong> <?= htmlspecialchars($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']) ?></p>
        <p><strong>Employment Type:</strong> <?= htmlspecialchars($emp['employment_type']) ?></p>
        <p><strong>Position:</strong> <?= htmlspecialchars($emp['position']) ?></p>
        <p><strong>Office:</strong> <?= htmlspecialchars($emp['office']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($emp['email']) ?></p>
        <p><strong>Date Hired:</strong> <?= htmlspecialchars($emp['date_hired']) ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($emp['status']) ?></p>
    </div>
    <div class="action-buttons text-right" style="max-width: 25%; display: flex; flex-direction: column; gap: 10px;">
        <button class="btn btn-sm btn-warning edit-btn"
            data-id="<?= $emp['employee_id'] ?>"
            data-employee_number="<?= htmlspecialchars($emp['employee_number']) ?>"
            data-first_name="<?= htmlspecialchars($emp['first_name']) ?>"
            data-middle_name="<?= htmlspecialchars($emp['middle_name']) ?>"
            data-last_name="<?= htmlspecialchars($emp['last_name']) ?>"
            data-employment_type="<?= htmlspecialchars($emp['employment_type']) ?>"
            data-position="<?= htmlspecialchars($emp['position']) ?>"
            data-office="<?= htmlspecialchars($emp['office']) ?>"
            data-email="<?= htmlspecialchars($emp['email']) ?>"
            data-date_hired="<?= htmlspecialchars($emp['date_hired']) ?>"
            data-status="<?= htmlspecialchars($emp['status']) ?>">
            Edit
        </button>
        <button class="btn btn-danger btn-sm delete-btn" data-id="<?= $emp['employee_id'] ?>">Delete</button>
    </div>
</div>

<!-- Setup Leave Credit Button -->
<div class="mb-3 text-right">
    <button id="initialSetupLeaveBtn" class="btn btn-primary">Setup Leave Credit</button>
</div>

<!-- Initial Setup Modal -->
<div class="modal fade" id="initialSetupLeaveModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form id="initialSetupLeaveForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Setup Leave Credit</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="setup_employee_id" name="employee_id" value="<?= $emp['employee_id'] ?>">
          <div class="form-group">
            <label for="leaveTypeSelect">Leave Type</label>
            <select id="leaveTypeSelect" name="leave_type_id" class="form-control" required>
              <!-- options loaded via AJAX -->
            </select>
          </div>
          <div class="form-group">
            <label for="initialCredits">Initial Credits</label>
            <input type="number" step="0.01" min="0" id="initialCredits" name="total_credits" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" id="initialSetupNextBtn" class="btn btn-primary">Next</button>

          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmSetupModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Setup</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p>You are about to give <strong><span id="confirmCredits"></span></strong> credits of <strong><span id="confirmTypeName"></span></strong> to this employee.</p>
        <input type="hidden" id="confirm_employee_id">
        <input type="hidden" id="confirm_leave_type_id">
        <input type="hidden" id="confirm_total_credits">
      </div>
      <div class="modal-footer">
        <button id="doSetupBtn" class="btn btn-success">Confirm</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Leave Credits Container -->
<div class="leave-credits-container" style="display: flex; gap: 15px; flex-wrap: wrap;">
    <?php if (count($leaveCredits) === 0): ?>
        <p>No leave credits found for this employee.</p>
    <?php else: ?>
        <?php foreach ($leaveCredits as $credit): ?>
            <div class="leave-credit-box clickable" 
                 data-leave-type="<?= htmlspecialchars($credit['leave_type_name']) ?>"
                 data-leave-type-id="<?= $credit['leave_type_id'] ?>"
                 data-credit-id="<?= $credit['credit_id'] ?>"
                 data-employee-id="<?= $employee_id ?>">
                <h5 style="margin-bottom: 10px; font-weight: bold;">
                    <?= htmlspecialchars($credit['leave_type_name']) ?>
                </h5>
                <p style="font-size: 1.3rem; margin: 0;">
                    <strong>Balance:</strong> <?= htmlspecialchars(number_format($credit['balance_credits'], 2)) ?>
                </p>
                <p style="font-size: 0.8rem; color: #666; margin-top: 10px;">
                    Updated: <?= htmlspecialchars(date('M d, Y', strtotime($credit['updated_at']))) ?>
                </p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
