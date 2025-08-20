<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Access control: Only HR users
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

// 1) Fetch employee personal details
$sqlEmp = "
    SELECT 
        employee_id, employee_number, first_name, middle_name, last_name,
        employment_type, position, office, email, date_hired, status
    FROM employees
    WHERE employee_id = ?
";
$stmtEmp = $conn->prepare($sqlEmp);
$stmtEmp->bind_param("i", $employee_id);
$stmtEmp->execute();
resultEmp:
$resultEmp = $stmtEmp->get_result();
if ($resultEmp->num_rows === 0) {
    echo 'Employee not found.';
    exit();
}
$emp = $resultEmp->fetch_assoc();
$stmtEmp->close();

// Build nicely capitalized full name with middle initial
$first   = ucwords(strtolower($emp['first_name']));
$middle  = $emp['middle_name']
           ? ' ' . strtoupper(substr($emp['middle_name'],0,1)) . '.'
           : '';
$last    = ucwords(strtolower($emp['last_name']));
$fullName = "$first$middle $last";

$sqlCredits = "
  SELECT 
    lc.credit_id,
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
    AND (lc.leave_type_id IN (1,2) OR lc.balance_credits > 0)
  ORDER BY 
    CASE 
      WHEN lc.leave_type_id = 1 THEN 0  -- Vacation
      WHEN lc.leave_type_id = 2 THEN 1  -- Sick
      ELSE 2
    END,
    lt.name
";
$stmtCredits = $conn->prepare($sqlCredits);
$stmtCredits->bind_param("i", $employee_id);
$stmtCredits->execute();
$resultCredits = $stmtCredits->get_result();
$otherCredits = [];
while ($row = $resultCredits->fetch_assoc()) {
    $otherCredits[] = $row;
}
$stmtCredits->close();

// 3) Fetch all CTO earnings separately
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
    AND balance > 0
  ORDER BY earned_at DESC
";
$stmtCto = $conn->prepare($sqlCto);
$stmtCto->bind_param("i", $employee_id);
$stmtCto->execute();
$resultCto = $stmtCto->get_result();
$ctoRows = [];
while ($row = $resultCto->fetch_assoc()) {
    $ctoRows[] = $row;
}
$stmtCto->close();
?>

<!-- Employee Personal Details -->
<div class="employee-details p-4 mb-4 bg-white rounded shadow-sm">
  <h4 class="mb-3">Personal Details</h4>

  <!-- Action buttons (Edit / Delete) -->
  <div class="d-flex justify-content-end mb-2 action-buttons">
    <button
      class="btn btn-warning btn-sm mr-2 edit-btn"
      title="Edit employee"
      data-id="<?= (int)$emp['employee_id'] ?>"
      data-employee_number="<?= htmlspecialchars($emp['employee_number'], ENT_QUOTES) ?>"
      data-first_name="<?= htmlspecialchars($emp['first_name'], ENT_QUOTES) ?>"
      data-middle_name="<?= htmlspecialchars($emp['middle_name'] ?? '', ENT_QUOTES) ?>"
      data-last_name="<?= htmlspecialchars($emp['last_name'], ENT_QUOTES) ?>"
      data-employment_type="<?= htmlspecialchars($emp['employment_type'], ENT_QUOTES) ?>"
      data-position="<?= htmlspecialchars($emp['position'], ENT_QUOTES) ?>"
      data-office="<?= htmlspecialchars($emp['office'], ENT_QUOTES) ?>"
      data-email="<?= htmlspecialchars($emp['email'], ENT_QUOTES) ?>"
      data-date_hired="<?= htmlspecialchars($emp['date_hired'], ENT_QUOTES) ?>"
      data-status="<?= htmlspecialchars($emp['status'], ENT_QUOTES) ?>"
    >
      Edit
    </button>

    <button
      class="btn btn-danger btn-sm delete-btn"
      title="Delete employee"
      data-id="<?= (int)$emp['employee_id'] ?>"
    >
      Delete
    </button>
  </div>

  <table class="table table-borderless mb-0">
    <tbody>
      <tr>
        <th scope="row" class="text-right">Name</th>
        <td><?= htmlspecialchars($fullName) ?></td>
      </tr>
      <tr>
        <th scope="row" class="text-right">Employment Type</th>
        <td><?= htmlspecialchars($emp['employment_type']) ?></td>
      </tr>
      <tr>
        <th scope="row" class="text-right">Position</th>
        <td><?= htmlspecialchars($emp['position']) ?></td>
      </tr>
      <tr>
        <th scope="row" class="text-right">Office</th>
        <td><?= htmlspecialchars($emp['office']) ?></td>
      </tr>
      <tr>
        <th scope="row" class="text-right">Email</th>
        <td><?= htmlspecialchars($emp['email']) ?></td>
      </tr>
      <tr>
        <th scope="row" class="text-right">Date Hired</th>
        <td><?= htmlspecialchars($emp['date_hired']) ?></td>
      </tr>
      <tr>
        <th scope="row" class="text-right">Status</th>
        <td><?= htmlspecialchars($emp['status']) ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- Setup Leave Credit Button -->
<div class="mb-3 text-right">
  <button id="initialSetupLeaveBtn" class="btn btn-primary">
    Setup Leave Credit
  </button>
</div>

<!-- Initial Setup Modal -->
<div class="modal fade" id="initialSetupLeaveModal" tabindex="-1" role="dialog"
     aria-labelledby="initialSetupLeaveModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="initialSetupLeaveForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="initialSetupLeaveModalLabel">
            Setup Leave Credit
          </h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="setup_employee_id" name="employee_id"
                 value="<?= $emp['employee_id'] ?>">
          <div class="form-group">
            <label for="leaveTypeSelect">Leave Type</label>
            <select id="leaveTypeSelect" name="leave_type_id"
                    class="form-control" required>
              <!-- options loaded via AJAX -->
            </select>
          </div>
          <div id="initialCreditsContainer" class="form-group">
            <label for="initialCredits">Initial Credits</label>
            <input type="number" step="0.01" min="0"
                   id="initialCredits" name="total_credits"
                   class="form-control">
          </div>
          <div id="ctoFieldsContainer"></div>
        </div>
        <div class="modal-footer">
          <button type="button" id="initialSetupNextBtn"
                  class="btn btn-primary">Next</button>
          <button type="button" class="btn btn-secondary"
                  data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmSetupModal" tabindex="-1" role="dialog"
     aria-labelledby="confirmSetupModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmSetupModalLabel">Confirm Setup</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p>
          You are about to give
          <strong><span id="confirmCredits"></span></strong>
          credits of
          <strong><span id="confirmTypeName"></span></strong>
          to this employee.
        </p>
        <input type="hidden" id="confirm_employee_id">
        <input type="hidden" id="confirm_leave_type_id">
        <input type="hidden" id="confirm_total_credits">
      </div>
      <div class="modal-footer">
        <button id="doSetupBtn" class="btn btn-success">Confirm</button>
        <button type="button" class="btn btn-secondary"
                data-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Leave Credits Container -->
<div class="leave-credits-section mb-4">
  <h4>Leave Credits</h4>
  <div class="leave-credits-container d-flex flex-wrap" style="gap:1rem;">
    <?php if (empty($otherCredits)): ?>
      <p>No leave credits found for this employee.</p>
    <?php else: ?>
      <?php foreach ($otherCredits as $credit): ?>
        <div class="leave-credit-box clickable"
             data-leave-type="<?= htmlspecialchars($credit['leave_type_name']) ?>"
             data-leave-type-id="<?= $credit['leave_type_id'] ?>"
             data-credit-id="<?= $credit['credit_id'] ?>"
             data-employee-id="<?= $employee_id ?>">
          <h5 class="m-0"><?= htmlspecialchars($credit['leave_type_name']) ?></h5>
          <p class="mb-1"><strong>Balance:</strong>
             <?= htmlspecialchars(number_format($credit['balance_credits'], 2)) ?></p>
          <p style="font-size:.8rem;color:#666;margin-top:.25rem;">
            Updated: <?= htmlspecialchars(date('M d, Y', strtotime($credit['updated_at']))) ?>
          </p>
        </div>
      <?php endforeach; ?>

      <!-- ADD NEW-CREDIT BOX -->
      <div class="leave-credit-box add-credit-box"
           onclick="$('#initialSetupLeaveBtn').click();">
        <h5>+</h5>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Compensatory Time-Off Container -->
<div class="cto-section mb-4">
  <h4>Compensatory Time-Off</h4>
  <div class="cto-container d-flex flex-wrap" style="gap:1rem;">

    <?php if (empty($ctoRows)): ?>
      <!-- Show Add CTO box even when no earnings yet -->
      <div class="leave-credit-box add-credit-box setup-leave-box"
           data-employee-id="<?= $employee_id ?>"
           data-leave-type-id="12"
           title="Add Compensatory Time-Off">
        <h5 class="m-0">+</h5>
        <small>Add CTO</small>
      </div>
    <?php else: ?>
      <?php foreach ($ctoRows as $cto): ?>
        <div class="leave-credit-box clickable"
             data-leave-type="Compensatory Time-Off"
             data-leave-type-id="12"
             data-credit-id="<?= $cto['cto_id'] ?>"
             data-employee-id="<?= $employee_id ?>">
          <h6><?= htmlspecialchars($cto['source']) ?></h6>
          <p><strong>Balance:</strong> <?= number_format($cto['balance'], 2) ?> days</p>
          <p style="font-size:.8rem;color:#666; margin-top:0.5rem;">
            Earned <?= date('M d, Y', strtotime($cto['earned_at'])) ?><br>
            Expires <?= date('M d, Y', strtotime($cto['expires_at'])) ?>
          </p>
        </div>
      <?php endforeach; ?>

      <!-- Keep Add CTO box even when there are existing earnings -->
      <div class="leave-credit-box add-credit-box setup-leave-box"
           data-employee-id="<?= $employee_id ?>"
           data-leave-type-id="12"
           title="Add Compensatory Time-Off">
        <h5 class="m-0">+</h5>
        <small>Add CTO</small>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- Hidden current employee id for AJAX refresh -->
<input type="hidden" id="current_employee_id" value="<?= (int)$employee_id ?>">

<!-- Leave Applications (employee-specific) -->
<div id="employeeLeaveAppsContainer" class="mb-4">
  <?php
    // Initial server-side render; keeps view consistent with AJAX refresh endpoint
    include __DIR__ . '/get_employee_leave_applications_table.php';
  ?>
</div>

<!-- Leave Credit Action Modal -->
<div class="modal fade" id="leaveCreditActionModal" tabindex="-1" role="dialog"
     aria-labelledby="leaveCreditModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="leaveCreditModalLabel">Leave Credit Options</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p id="leaveTypeName" class="font-weight-bold"></p>
        <input type="hidden" id="modalEmployeeId">
        <input type="hidden" id="modalLeaveType">
        <input type="hidden" id="modalCreditId">

        <div class="d-flex justify-content-between">
          <button class="btn btn-primary" id="addLeaveCreditBtn">Add Leave Credit</button>
          <button class="btn btn-success" id="applyLeaveBtn">Apply Leave</button>
          <button class="btn btn-danger" id="deductLeaveCreditBtn">Deduct Credit</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Deduct Leave Credit Modal -->
<div class="modal fade" id="deductLeaveCreditModal" tabindex="-1" role="dialog"
     aria-labelledby="deductLeaveCreditModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="deductLeaveCreditForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deductLeaveCreditModalLabel">Deduct Leave Credit</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="employee_id"   id="deduct_employee_id">
          <input type="hidden" name="leave_type_id" id="deduct_leave_type_id">
          <input type="hidden" name="credit_id"     id="deduct_credit_id">

          <div class="form-group">
            <label>Number of Days to Deduct</label>
            <input type="number" step="0.01" min="0" class="form-control" name="days_to_deduct" required>
          </div>
          <div class="form-group">
            <label>Reason</label>
            <textarea class="form-control" name="reason" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-danger">Deduct</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php
