<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// --- Access control: HR only ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'hr') {
    http_response_code(403);
    echo "<div class='alert alert-danger mb-0'>Unauthorized</div>";
    return;
}

// --- Resolve employee_id (from GET or parent scope) ---
$employee_id = isset($_GET['employee_id'])
    ? (int) $_GET['employee_id']
    : (isset($employee_id) ? (int) $employee_id : 0);

if ($employee_id <= 0) {
    http_response_code(400);
    echo "<div class='alert alert-warning mb-0'>Invalid employee ID.</div>";
    return;
}

// --- Optional: status filter ---
$statusFilter = '';
$params = [$employee_id];
$types  = 'i';

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $allowed = ['Pending','Approved','Rejected','Cancelled'];
    if (in_array($_GET['status'], $allowed, true)) {
        $statusFilter = ' AND la.status = ? ';
        $params[] = $_GET['status'];
        $types   .= 's';
    }
}

// --- Query: pull with approver info ---
$sql = "
  SELECT
    la.application_id,
    la.leave_type_id,
    lt.name AS leave_type,
    la.number_of_days,
    la.status,
    la.created_at,
    la.approved_by,
    CONCAT(hrEmp.first_name, ' ', hrEmp.last_name) AS approver_name,
    MAX(CASE WHEN lad.field_name = 'date_from'      THEN lad.field_value END) AS date_from,
    MAX(CASE WHEN lad.field_name = 'date_to'        THEN lad.field_value END) AS date_to,
    MAX(CASE WHEN lad.field_name = 'effective_date' THEN lad.field_value END) AS effective_date
  FROM leave_applications la
  JOIN leave_types lt
    ON lt.leave_type_id = la.leave_type_id
  LEFT JOIN leave_application_details lad
    ON lad.application_id = la.application_id
  LEFT JOIN users hrUser
    ON hrUser.user_id = la.approved_by
  LEFT JOIN employees hrEmp
    ON hrEmp.employee_id = hrUser.employee_id
  WHERE la.employee_id = ?
  $statusFilter
  GROUP BY la.application_id, la.leave_type_id, lt.name, la.number_of_days, la.status, la.created_at, la.approved_by, hrEmp.first_name, hrEmp.last_name
  ORDER BY la.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

// helper to format date safely
function fmt_date($val, $fmt = 'Y-m-d') {
    if (!$val) return '';
    $t = strtotime($val);
    return $t ? date($fmt, $t) : htmlspecialchars($val, ENT_QUOTES);
}
?>

<h4>Leave Applications</h4>
<div class="table-responsive">
  <table class="table table-bordered table-hover">
    <thead class="thead-light">
      <tr>
        <th>ID</th>
        <th>Type</th>
        <th>From</th>
        <th>To</th>
        <th>Days</th>
        <th>Status</th>
        <th>Approved By</th>
        <th>Filed</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($res->num_rows > 0): ?>
        <?php while ($row = $res->fetch_assoc()):
          $from = $row['date_from'] ?: $row['effective_date'] ?: '';
          $to   = $row['date_to']   ?: $row['effective_date'] ?: '';

          // Status badge
          $status = $row['status'];
          $badgeClass = 'secondary';
          if ($status === 'Approved') $badgeClass = 'success';
          elseif ($status === 'Pending') $badgeClass = 'warning';
          elseif ($status === 'Rejected') $badgeClass = 'danger';
          elseif ($status === 'Cancelled') $badgeClass = 'dark';

          // Approver display
          $approverDisplay = '—';
          if ($status === 'Approved' && !empty($row['approver_name'])) {
              $approverDisplay = htmlspecialchars($row['approver_name']);
          }
        ?>
          <tr>
            <td data-label="ID"><?= (int)$row['application_id'] ?></td>
            <td data-label="Type"><?= htmlspecialchars($row['leave_type'], ENT_QUOTES) ?></td>
            <td data-label="From"><?= $from ? htmlspecialchars(fmt_date($from)) : '—' ?></td>
            <td data-label="To"><?= $to   ? htmlspecialchars(fmt_date($to))   : '—' ?></td>
            <td data-label="Days"><?= htmlspecialchars($row['number_of_days'], ENT_QUOTES) ?></td>
            <td data-label="Status">
              <span class="badge badge-<?= $badgeClass ?>">
                <?= htmlspecialchars($status) ?>
              </span>
            </td>
            <td data-label="Approved By"><?= $approverDisplay ?></td>
            <td data-label="Filed"><?= htmlspecialchars(fmt_date($row['created_at'], 'Y-m-d H:i'), ENT_QUOTES) ?></td>
            <td data-label="Actions">
              <button
                class="btn btn-sm btn-info view-application-btn"
                data-application-id="<?= (int)$row['application_id'] ?>">
                View
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="9" class="text-center">No leave applications found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
