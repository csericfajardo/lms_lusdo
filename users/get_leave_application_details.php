<?php
require_once __DIR__ . '/../config/database.php';

$appId = (int)($_GET['application_id'] ?? 0);
if ($appId <= 0) {
    http_response_code(400);
    echo "<p class='text-danger'>Invalid application ID.</p>";
    exit;
}

// Fetch main record
$sqlApp = "
  SELECT 
    la.application_id,
    la.number_of_days,
    la.status,
    e.employee_number,
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    lt.name AS leave_type
  FROM leave_applications la
  JOIN employees e ON la.employee_id = e.employee_id
  JOIN leave_types lt ON la.leave_type_id = lt.leave_type_id
  WHERE la.application_id = ?
  LIMIT 1
";
$stmtApp = $conn->prepare($sqlApp);
$stmtApp->bind_param('i', $appId);
$stmtApp->execute();
$app = $stmtApp->get_result()->fetch_assoc();
$stmtApp->close();

if (!$app) {
    echo "<p class='text-warning'>No details found for application #{$appId}.</p>";
    exit;
}

// Fetch dynamic fields
$sqlDet = "SELECT field_name, field_value FROM leave_application_details WHERE application_id = ? ORDER BY detail_id";
$stmtDet = $conn->prepare($sqlDet);
$stmtDet->bind_param('i', $appId);
$stmtDet->execute();
$details = $stmtDet->get_result();
$stmtDet->close();

// Render form
?>
<form id="editLeaveApplicationForm">
  <input type="hidden" name="application_id" value="<?= $app['application_id'] ?>">
  <div class="form-group row">
    <label class="col-sm-4 col-form-label">Application #</label>
    <div class="col-sm-8">
      <p class="form-control-plaintext"><?= $app['application_id'] ?></p>
    </div>
  </div>
  <div class="form-group row">
    <label class="col-sm-4 col-form-label">Employee</label>
    <div class="col-sm-8">
      <p class="form-control-plaintext"><?= htmlspecialchars($app['employee_number'] . ' – ' . $app['employee_name']) ?></p>
    </div>
  </div>
  <div class="form-group row">
    <label class="col-sm-4 col-form-label">Leave Type</label>
    <div class="col-sm-8">
      <p class="form-control-plaintext"><?= htmlspecialchars($app['leave_type']) ?></p>
    </div>
  </div>
  <div class="form-group row">
    <label for="number_of_days" class="col-sm-4 col-form-label">Days</label>
    <div class="col-sm-8">
      <input type="number" step="0.01" min="0" class="form-control" name="number_of_days" id="number_of_days" value="<?= $app['number_of_days'] ?>" required>
    </div>
  </div>
  <div class="form-group row">
    <label for="status" class="col-sm-4 col-form-label">Status</label>
    <div class="col-sm-8">
      <select name="status" id="status" class="form-control" required>
        <?php foreach (['Pending','Approved','Cancelled','Rejected'] as $st): ?>
          <option value="<?= $st ?>" <?= $app['status']==$st?'selected':'' ?>><?= $st ?></option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <?php while ($row = $details->fetch_assoc()): 
    $field = $row['field_name'];
    $label = ucwords(str_replace('_',' ',$field));
    $value = htmlspecialchars($row['field_value']);
  ?>
    <div class="form-group row">
      <label for="<?= $field ?>" class="col-sm-4 col-form-label"><?= $label ?></label>
      <div class="col-sm-8">
        <input type="text" class="form-control" name="details[<?= $field ?>]" id="<?= $field ?>" value="<?= $value ?>">
      </div>
    </div>
  <?php endwhile; ?>

  <div class="modal-footer">
    <button type="submit" class="btn btn-primary">Update</button>
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
  </div>
</form>
