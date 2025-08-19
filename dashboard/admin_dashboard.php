<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: /depedlu_lms/auth/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/depedlu_lms/css/admin_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<!-- Hamburger menu for mobile -->
<button class="sidebar-toggle" onclick="toggleSidebar()">☰ Menu</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h4 class="text-center">Admin</h4>
    <a href="#" onclick="showTab('home')">Dashboard Home</a>
    <a href="#" onclick="showTab('manage_hr')">Manage HR</a>
    <a href="#" onclick="showTab('reports')">Reports</a>
    <a href="/depedlu_lms/auth/logout.php">Logout</a>
</div>

<!-- Main Content -->
<div class="content">
    <div id="home" class="tab-content">
        <h2>Welcome to Admin Dashboard</h2>
        <p>Overview and management modules for DepEd La Union LMS.</p>
    </div>

    <!-- Manage HR Tab -->
    <div id="manage_hr" class="tab-content" style="display:none;">
        <h2>Manage HR</h2>
        <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addHRModal">Add HR</button>
        <div id="hrTable">
            <?php include '../users/get_hr_table.php'; ?>
        </div>
    </div>

    <!-- Reports Tab -->
    <div id="reports" class="tab-content" style="display:none;">
        <h2>Reports</h2>
        <p>Reports module here.</p>
    </div>
</div>

<!-- Add/Edit HR Modal -->
<div class="modal fade" id="addHRModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="addHRForm">
        <div class="modal-header">
          <h5 class="modal-title">Add/Edit HR</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body row g-3">
          <!-- Username -->
          <div class="col-md-6">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
          </div>

          <!-- Email -->
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>

          <!-- Password -->
          <div class="col-md-6">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control">
            <small class="text-muted">(Leave blank to keep current password when editing)</small>
          </div>

          <!-- Status -->
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <!-- Employee Number -->
          <div class="col-md-6">
            <label class="form-label">Employee #</label>
            <input type="text" name="employee_number" class="form-control" required>
          </div>

          <!-- First Name -->
          <div class="col-md-6">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" required>
          </div>

          <!-- Middle Name -->
          <div class="col-md-6">
            <label class="form-label">Middle Name</label>
            <input type="text" name="middle_name" class="form-control">
          </div>

          <!-- Last Name -->
          <div class="col-md-6">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
          </div>

          <!-- Position -->
          <div class="col-md-6">
            <label class="form-label">Position</label>
            <input type="text" name="position" class="form-control" required>
          </div>

          <!-- Office -->
          <div class="col-md-6">
            <label class="form-label">Office</label>
            <input type="text" name="office" class="form-control" required>
          </div>

          <!-- Employment Type -->
          <div class="col-md-6">
            <label class="form-label">Employment Type</label>
            <select name="employment_type" class="form-control" required>
              <option value="Teaching">Teaching</option>
              <option value="Non-Teaching">Non-Teaching</option>
            </select>
          </div>

          <!-- Date Hired -->
          <div class="col-md-6">
            <label class="form-label">Date Hired</label>
            <input type="date" name="date_hired" class="form-control" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="/depedlu_lms/js/admin_dashboard_script.js"></script>

<script>
function showTab(tabId) {
    var contents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < contents.length; i++) {
        contents[i].style.display = "none";
    }
    document.getElementById(tabId).style.display = "block";

    // Update URL hash
    window.location.hash = tabId;

    // Hide sidebar after click on mobile
    if (window.innerWidth <= 768) {
        document.getElementById("sidebar").classList.remove("show");
    }
}

window.onload = function() {
    if (window.location.hash) {
        showTab(window.location.hash.substring(1));
    } else {
        showTab('home');
    }
}

function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("show");
}
</script>

</body>
</html>
