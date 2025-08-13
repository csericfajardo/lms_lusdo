<?php
  // Assuming session contains employee info
  session_start();
  if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'employee') {
    header('Location: /auth/login.php');
    exit();
  }
  $employee = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Employee Dashboard — DepEd LU LMS</title>

  <!-- Optional: if your project already includes hr_dashboard.css, it will visually match -->
  <link rel="stylesheet" href="/css/hr_dashboard.css" />
  <link rel="stylesheet" href="/css/employee_dashboard_style.css" />
</head>
<body>
  <!-- Mobile-first header: left = person + logout, right = heading -->
  <header class="edl-header">
    <div class="edl-header-left">
      <button id="btnPersonal" class="icon-btn" title="Personal Details" aria-label="Personal Details">
        <!-- Person icon (SVG, no external dependency) -->
        <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 12c2.761 0 5-2.686 5-6s-2.239-6-5-6-5 2.686-5 6 2.239 6 5 6zm0 2c-4.418 0-8 2.239-8 5v3h16v-3c0-2.761-3.582-5-8-5z"/>
        </svg>
      </button>
      <form method="post" action="/auth/logout.php" class="logout-form">
        <button type="submit" class="logout-btn" title="Logout" aria-label="Logout">Logout</button>
      </form>
    </div>
    <div class="edl-header-right">DepEd LU LMS</div>
  </header>

  <!-- Row: Employee number and name -->
  <section class="edl-identity">
    <div class="edl-identity-line">
      <span class="lbl">Employee No.:</span>
      <span class="val" id="employeeNo"><?php echo htmlspecialchars($employee['employee_no'] ?? '—'); ?></span>
    </div>
    <div class="edl-identity-line">
      <span class="lbl">Name:</span>
      <span class="val" id="employeeName"><?php echo htmlspecialchars($employee['name'] ?? '—'); ?></span>
    </div>
  </section>

  <!-- Leave Credits -->
  <section class="edl-section">
    <h2 class="edl-section-title">Leave Credits</h2>
    <div id="leaveCreditsGrid" class="edl-grid-2"></div>
  </section>

  <!-- CTO Credits -->
  <section class="edl-section">
    <h2 class="edl-section-title">Compensatory Time Off Credits</h2>
    <div id="ctoCreditsGrid" class="edl-grid-2"></div>
  </section>

  <!-- Leave Applications Table -->
  <section class="edl-section">
    <h2 class="edl-section-title">Leave Applications</h2>
    <div class="edl-table-wrap">
      <table class="edl-table" id="applicationsTable" aria-label="Leave Applications">
        <thead>
          <tr>
            <th>Ref #</th>
            <th>Type</th>
            <th>Date/s</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="applicationsBody">
          <!-- Populated by JS -->
        </tbody>
      </table>
    </div>
  </section>

  <!-- Modal: Personal Details -->
  <dialog id="personalModal" class="edl-modal">
    <form method="dialog" class="edl-modal-card">
      <h3>Personal Details</h3>
      <div class="edl-kv">
        <div><span class="k">Employee No.</span><span class="v" id="pdEmployeeNo"></span></div>
        <div><span class="k">Name</span><span class="v" id="pdName"></span></div>
        <div><span class="k">Email</span><span class="v" id="pdEmail"></span></div>
        <div><span class="k">Position</span><span class="v" id="pdPosition"></span></div>
        <div><span class="k">Employment Type</span><span class="v" id="pdEmpType"></span></div>
      </div>
      <div class="edl-modal-actions">
        <button value="close" class="btn">Close</button>
      </div>
    </form>
  </dialog>

  <!-- Modal: Apply Leave (dynamic per leave type) -->
  <dialog id="applyModal" class="edl-modal">
    <form id="applyForm" class="edl-modal-card">
      <h3 id="applyModalTitle">Apply for Leave</h3>
      <input type="hidden" name="leave_type" id="applyLeaveType" />
      <div id="applyDynamicFields"></div>
      <div class="edl-modal-actions">
        <button type="button" id="applyCancel" class="btn btn-ghost">Close</button>
        <button type="submit" class="btn btn-primary">Submit Application</button>
      </div>
    </form>
  </dialog>

  <!-- Modal: View Application -->
  <dialog id="viewModal" class="edl-modal">
    <form method="dialog" class="edl-modal-card">
      <h3>Application Details</h3>
      <div id="viewDetails" class="edl-kv"></div>
      <div class="edl-modal-actions">
        <button value="close" class="btn btn-ghost">Close</button>
        <button type="button" id="btnCancelApplication" class="btn btn-danger">Cancel Application</button>
      </div>
    </form>
  </dialog>

  <script>
    // Base URLs for existing endpoints (adjust if your routing differs)
    window.EDL_ENDPOINTS = {
      employeeDetails: '/users/get_employee_details.php',
      availableLeaveTypes: '/users/get_available_leave_types.php',
      ctoCredits: '/users/get_cto_earnings.php',
      applyLeave: '/users/apply_leave.php',
      applicationsTable: '/users/get_leave_applications_table.php',
      cancelLeave: '/users/update_leave_application.php', // will post action=cancel
      notify: '/users/_notify.php'
    };
  </script>
  <script src="/js/employee_dashboard_script.js"></script>
</body>
</html>
