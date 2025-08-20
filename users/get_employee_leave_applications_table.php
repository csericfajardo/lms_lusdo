<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

/**
 * Returns ONLY the HTML table of leave applications for one employee.
 * Can be included server-side (inherits $employee_id) or fetched via AJAX (?employee_id=123).
 */

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

// --- Optional: status filter (?status=Pending|Approved|Rejected|Cancelled) ---
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

// --- Query: pull common date fields from leave_application_details (pivot) ---
$sql = "
  SELECT
    la.application_id,
    la.leave_type_id,
    lt.name AS leave_type,
    la.number_of_days,
    la.status,
    la.created_at,
    MAX(CASE WHEN lad.field_name = 'date_from'      THEN lad.field_value END) AS date_from,
    MAX(CASE WHEN lad.field_name = 'date_to'        THEN lad.field_value END) AS date_to,
    MAX(CASE WHEN lad.field_name = 'effective_date' THEN lad.field_value END) AS effective_date
  FROM leave_applications la
  JOIN leave_types lt
    ON lt.leave_type_id = la.leave_type_id
  LEFT JOIN leave_application_details lad
    ON lad.application_id = la.application_id
  WHERE la.employee_id = ?
  $statusFilter
  GROUP BY la.application_id, la.leave_type_id, lt.name, la.number_of_days, la.status, la.created_at
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
        <th>Filed</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($res->num_rows > 0): ?>
        <?php while ($row = $res->fetch_assoc()):
          // Prefer date_from/date_to; fall back to effective_date if range not applicable
          $from = $row['date_from'] ?: $row['effective_date'] ?: '';
          $to   = $row['date_to']   ?: $row['effective_date'] ?: '';
        ?>
          <tr>
            <td><?= (int)$row['application_id'] ?></td>
            <td><?= htmlspecialchars($row['leave_type'], ENT_QUOTES) ?></td>
            <td><?= $from ? htmlspecialchars(fmt_date($from)) : '—' ?></td>
            <td><?= $to   ? htmlspecialchars(fmt_date($to))   : '—' ?></td>
            <td><?= htmlspecialchars($row['number_of_days'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($row['status'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars(fmt_date($row['created_at'], 'Y-m-d H:i'), ENT_QUOTES) ?></td>
            <td>
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
          <td colspan="8" class="text-center">No leave applications found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
