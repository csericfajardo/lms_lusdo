<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employee') {
    header("Location: /depedlu_lms/auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee Dashboard - DepEd LU LMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/depedlu_lms/css/employee_dashboard_style.css">
</head>
<body>

<header class="edl-header">
    <div class="edl-header-left">
        <div class="edl-app-title">DepEd LU LMS</div>
        <div class="edl-identity-inline">
            <span id="empNo">—</span> | <span id="empName">—</span>
        </div>
    </div>
    <div class="edl-header-right">
        <button id="btnPersonal" class="icon-btn" title="Personal Details" aria-label="Personal Details">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.761 0 5-2.686 5-6s-2.239-6-5-6-5 2.686-5 6 2.239 6 5 6zm0 2c-4.418 0-8 2.239-8 5v3h16v-3c0-2.761-3.582-5-8-5z"/>
            </svg>
        </button>
        <form method="post" action="/depedlu_lms/auth/logout.php" class="logout-form">
            <button type="submit" class="logout-btn">Logout</button>
        </form>
    </div>
</header>

<!-- Leave Credits -->
<section class="edl-section">
    <h2 class="edl-section-title">Leave Credits</h2>
    <div id="leaveCreditsGrid" class="edl-grid-2"></div>
</section>

<!-- CTO Credits -->
<section class="edl-section">
    <h2 class="edl-section-title">Compensatory Time-Off Credits</h2>
    <div id="ctoCreditsGrid" class="edl-grid-2"></div>
</section>

<!-- Leave Applications -->
<section class="edl-section">
    <h2 class="edl-section-title">Leave Applications</h2>
    <div class="edl-table-wrap">
        <table class="edl-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Approver</th>
                    <th>Dates</th>
                </tr>
            </thead>
            <tbody id="applicationsBody"></tbody>
        </table>
    </div>
</section>

<!-- Personal Details Modal -->
<dialog id="personalModal" class="edl-modal">
    <div class="edl-modal-card">
        <h3>Personal Details</h3>
        <div class="edl-kv">
            <span class="k">Employee No:</span>
            <span class="v" id="pdEmployeeNo">—</span>
            <span class="k">Name:</span>
            <span class="v" id="pdName">—</span>
            <span class="k">Email:</span>
            <span class="v" id="pdEmail">—</span>
            <span class="k">Position:</span>
            <span class="v" id="pdPosition">—</span>
            <span class="k">Employment Type:</span>
            <span class="v" id="pdEmpType">—</span>
        </div>
        <div class="edl-modal-actions">
            <button type="button" onclick="document.getElementById('personalModal').close()">Close</button>
        </div>
    </div>
</dialog>

<!-- Apply Leave Modal -->
<dialog id="applyModal" class="edl-modal">
    <div class="edl-modal-card">
        <h3 id="applyModalTitle">Apply for Leave</h3>
        <form id="applyForm">
            <input type="hidden" name="leave_type" id="applyLeaveType">
            <div id="applyDynamicFields"></div>
            <div class="edl-modal-actions">
                <button type="button" id="applyCancel">Cancel</button>
                <button type="submit" class="btn-primary">Submit</button>
            </div>
        </form>
    </div>
</dialog>

<!-- View Application Modal -->
<dialog id="viewModal" class="edl-modal">
    <div class="edl-modal-card">
        <h3>Application Details</h3>
        <div id="viewDetails"></div>
        <div class="edl-modal-actions">
            <button type="button" id="btnCancelApplication" class="btn-danger">Cancel Application</button>
            <button type="button" onclick="document.getElementById('viewModal').close()">Close</button>
        </div>
    </div>
</dialog>

<script>
window.EDL_ENDPOINTS = {
    employeeDetails: '/depedlu_lms/users/empdash_get_details.php',
    availableLeaveTypes: '/depedlu_lms/users/empdash_get_leave_types.php',
    ctoCredits: '/depedlu_lms/users/empdash_get_cto.php',
    applyLeave: '/depedlu_lms/users/apply_leave.php',
    applicationsTable: '/depedlu_lms/users/empdash_get_applications.php',
    cancelLeave: '/depedlu_lms/users/update_leave_application.php',
    notify: '/depedlu_lms/users/_notify.php'
};
</script>
<script src="/depedlu_lms/js/employee_dashboard_script.js"></script>
</body>
</html>
