<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Allow admin or super_admin to view (supports direct AJAX loads)
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo '<div class="alert alert-danger mb-0">Unauthorized</div>';
    exit();
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Fetch HR users with optional linked employee info
$sql = "
  SELECT
    u.user_id,
    u.username,
    u.email,
    u.status,
    u.created_at,
    u.employee_id,
    e.employee_number,
    e.first_name,
    e.middle_name,
    e.last_name,
    e.position,
    e.office,
    e.employment_type,
    e.date_hired
  FROM users u
  LEFT JOIN employees e ON e.employee_id = u.employee_id
  WHERE u.role = 'hr'
  ORDER BY u.created_at DESC, u.user_id DESC
";

$res = $conn->query($sql);

// Helper: format name with middle initial if present
$formatName = function($row) {
    $first  = trim($row['first_name'] ?? '');
    $mid    = trim($row['middle_name'] ?? '');
    $last   = trim($row['last_name'] ?? '');

    if ($first === '' && $last === '') return '';
    $mi = $mid !== '' ? ' ' . strtoupper(mb_substr($mid, 0, 1)) . '.' : '';
    return ucwords(strtolower($first)) . $mi . ' ' . ucwords(strtolower($last));
};
?>
<div class="table-responsive">
  <table class="table table-bordered table-striped table-hover mb-0">
    <thead>
      <tr>
        <th style="white-space:nowrap;">Username</th>
        <th>Email</th>
        <th style="white-space:nowrap;">Employee #</th>
        <th>Name</th>
        <th style="white-space:nowrap;">Status</th>
        <th style="white-space:nowrap;">Created</th>
        <th style="width:140px; white-space:nowrap;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($res && $res->num_rows > 0): ?>
        <?php while ($row = $res->fetch_assoc()): ?>
          <?php
            $name      = $formatName($row);
            $empNo     = $row['employee_number'] ?? '';
            $statusRaw = strtolower($row['status'] ?? '');
            $statusLbl = $statusRaw !== '' ? ucwords($statusRaw) : '—';
            $created   = $row['created_at'] ? date('M d, Y', strtotime($row['created_at'])) : '—';
          ?>
          <tr>
            <td><?= h($row['username']) ?></td>
            <td><?= h($row['email']) ?></td>
            <td><?= $empNo !== '' ? h($empNo) : '—' ?></td>
            <td><?= $name !== '' ? h($name) : '—' ?></td>
            <td><?= h($statusLbl) ?></td>
            <td><?= h($created) ?></td>
            <td>
              <button
                class="btn btn-sm btn-warning edit-hr-btn"
                title="Edit HR"
                data-id="<?= (int)$row['user_id'] ?>"
                data-username="<?= h($row['username']) ?>"
                data-email="<?= h($row['email']) ?>"
                data-status="<?= h($statusRaw ?: 'active') ?>"
                data-employee_id="<?= (int)($row['employee_id'] ?? 0) ?>"
                data-employee_number="<?= h($row['employee_number'] ?? '') ?>"
                data-first_name="<?= h($row['first_name'] ?? '') ?>"
                data-middle_name="<?= h($row['middle_name'] ?? '') ?>"
                data-last_name="<?= h($row['last_name'] ?? '') ?>"
                data-position="<?= h($row['position'] ?? '') ?>"
                data-office="<?= h($row['office'] ?? '') ?>"
                data-employment_type="<?= h($row['employment_type'] ?? '') ?>"
                data-date_hired="<?= h($row['date_hired'] ?? '') ?>">
                Edit
              </button>
              <button
                class="btn btn-sm btn-danger delete-hr-btn"
                title="Delete HR"
                data-id="<?= (int)$row['user_id'] ?>">
                Delete
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="text-center text-muted">No HR users found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
