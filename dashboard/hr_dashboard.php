<?php
session_start();
require_once '../config/database.php';

// Access control: only HR users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// Fetch some example HR dashboard data (pending leaves count)
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM leave_applications WHERE status = 'Pending'");
$stmt->execute();
$pendingLeave = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>HR Dashboard - DepEd La Union LMS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../css/hr_dashboard.css" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>
<body>

<!-- Hamburger menu for mobile -->
<button class="sidebar-toggle btn btn-dark" onclick="toggleSidebar()">☰ Menu</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h4 class="text-center">HR</h4>
    <a href="#" onclick="showTab('home')">Dashboard Home</a>
    <a href="#" onclick="showTab('manage_leave')">Manage Leave</a>
    <a href="#" onclick="showTab('employees')">Employees</a>
    <a href="#" onclick="showTab('leave_types')">Leave Types</a>
    <a href="#" onclick="showTab('reports')">Reports</a>
    <a href="../auth/logout.php">Logout (<?php echo htmlspecialchars($username); ?>)</a>
</div>

<!-- Main Content -->
<div class="content">
    <div id="home" class="tab-content">
        <h2>Welcome to HR Dashboard</h2>
        <div class="cards">
            <div class="card">
                <h3>Pending Leaves</h3>
                <p><?php echo $pendingLeave; ?></p>
                <a href="#" onclick="showTab('manage_leave')">View</a>
            </div>
            <div class="card">
                <h3>Employees</h3>
                <p>Manage employee records</p>
                <a href="#" onclick="showTab('employees')">Manage</a>
            </div>
            <div class="card">
                <h3>Leave Types</h3>
                <p>View and manage leave types</p>
                <a href="#" onclick="showTab('leave_types')">Manage</a>
            </div>
        </div>
    </div>

<!--managing leaves section -->
<div id="manage_leave" class="tab-content" style="display:none;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Manage Leave Applications</h2>
        
    </div>
    <button class="btn btn-primary" data-toggle="modal" data-target="#addLeaveModal">Apply Leave</button>
    <div class="d-flex justify-content-end mb-2">
        <select id="filterStatus" class="form-control w-auto">
            <option value="">All Statuses</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
            <option value="Cancelled">Cancelled</option>
        </select>
    </div>

    <div id="leaveTable">
        <!-- Leave applications table loaded here -->
    </div>
</div>

<!--end of manage leave section-->


    <div id="employees" class="tab-content" style="display:none;">
        <h2>Employees</h2>
<button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addEmployeeModal">Add Employee</button>

<div id="employeesTableContainer">
    <?php include '../users/get_employees_table.php'; ?>
</div>
<div id="employeeDetailsContainer" style="margin-top: 20px; display:none;">
  <!-- Personal details will load here -->
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" role="dialog" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="addEmployeeForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Employee</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Employee Number -->
          <div class="form-group">
            <label>Employee Number</label>
            <input type="text" class="form-control" name="employee_number" required>
          </div>
          <!-- First Name -->
          <div class="form-group">
            <label>First Name</label>
            <input type="text" class="form-control" name="first_name" required>
          </div>
          <!-- Middle Name -->
          <div class="form-group">
            <label>Middle Name</label>
            <input type="text" class="form-control" name="middle_name">
          </div>
          <!-- Last Name -->
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" class="form-control" name="last_name" required>
          </div>
          <!-- Employment Type -->
          <div class="form-group">
            <label>Employment Type</label>
            <select class="form-control" name="employment_type" required>
              <option value="Teaching">Teaching</option>
              <option value="Non-Teaching">Non-Teaching</option>
            </select>
          </div>
          <!-- Position -->
          <div class="form-group">
            <label>Position</label>
            <input type="text" class="form-control" name="position" required>
          </div>
          <!-- Office -->
          <div class="form-group">
            <label>Office</label>
            <input type="text" class="form-control" name="office" required>
          </div>
          <!-- Date Hired -->
          <div class="form-group">
            <label>Date Hired</label>
            <input type="date" class="form-control" name="date_hired" required>
          </div>
          <!-- Status -->
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status" required>
              <option value="Active">Active</option>
              <option value="Retired">Retired</option>
              <option value="Separated">Separated</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
          <hr>
          <!-- Email -->
          <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <!-- Password -->
          <div class="form-group">
            <label>Password</label>
            <input type="password" class="form-control" name="password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Add Employee</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" role="dialog" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="editEmployeeForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Employee</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="employee_id" id="edit_employee_id">
          <!-- Employee Number (readonly to avoid accidental change) -->
          <div class="form-group">
            <label>Employee Number</label>
            <input type="text" class="form-control" name="employee_number" id="edit_employee_number" readonly>
          </div>
          <!-- First Name -->
          <div class="form-group">
            <label>First Name</label>
            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
          </div>
          <!-- Middle Name -->
          <div class="form-group">
            <label>Middle Name</label>
            <input type="text" class="form-control" name="middle_name" id="edit_middle_name">
          </div>
          <!-- Last Name -->
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
          </div>
          <!-- Employment Type -->
          <div class="form-group">
            <label>Employment Type</label>
            <select class="form-control" name="employment_type" id="edit_employment_type" required>
              <option value="Teaching">Teaching</option>
              <option value="Non-Teaching">Non-Teaching</option>
            </select>
          </div>
          <!-- Position -->
          <div class="form-group">
            <label>Position</label>
            <input type="text" class="form-control" name="position" id="edit_position" required>
          </div>
          <!-- Office -->
          <div class="form-group">
            <label>Office</label>
            <input type="text" class="form-control" name="office" id="edit_office" required>
          </div>
          <!-- Email -->
          <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email" id="edit_email" required>
          </div>
          <!-- Date Hired -->
          <div class="form-group">
            <label>Date Hired</label>
            <input type="date" class="form-control" name="date_hired" id="edit_date_hired" required>
          </div>
          <!-- Status -->
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status" id="edit_status" required>
              <option value="Active">Active</option>
              <option value="Retired">Retired</option>
              <option value="Separated">Separated</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
          <hr>
          <!-- New Password (optional) -->
          <div class="form-group">
            <label>New Password (leave blank to keep current)</label>
            <input type="password" class="form-control" name="password" id="edit_password">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Update Employee</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Leave Credit Action Modal -->
<div class="modal fade" id="leaveCreditActionModal" tabindex="-1" role="dialog" aria-labelledby="leaveCreditModalLabel" aria-hidden="true">
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
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Leave Credit Modal -->
<div class="modal fade" id="addLeaveCreditModal" tabindex="-1" role="dialog" aria-labelledby="addLeaveCreditModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="addLeaveCreditForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Leave Credit</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <!-- Hidden Fields -->
          <input type="hidden" name="employee_id" id="credit_employee_id">
          <input type="hidden" name="leave_type_id" id="credit_leave_type_id">

          <!-- Leave Type (readonly) -->
          <div class="form-group">
            <label>Leave Type</label>
            <input type="text" class="form-control" id="credit_leave_type_name" readonly>
          </div>

          <!-- Total Credits -->
          <div class="form-group">
            <label>Total Credits to Add</label>
            <input type="number" step="0.01" min="0" class="form-control" name="total_credits" required>
          </div>

          <!-- Reason for Adding -->
          <div class="form-group">
            <label>Reason</label>
            <textarea class="form-control" name="reason" rows="3" required placeholder="Enter the reason for adding leave credits..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Add Credit</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Apply Leave Modal -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1" role="dialog" aria-labelledby="applyLeaveModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="applyLeaveForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Apply for Leave</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
          
        </div>

        <div class="modal-body">
          <!-- Hidden fields -->
          <input type="hidden" name="employee_id" id="apply_leave_employee_id">
          <input type="hidden" name="leave_type_id" id="apply_leave_type_id">

          <!-- Display leave type name -->
          <div class="form-group">
            <label>Leave Type</label>
            <input type="text" class="form-control" id="apply_leave_type_name" readonly>
          </div>
          <!-- Leave Status Dropdown -->
<div class="form-group">
  <label>Leave Status</label>
  <select name="status" id="apply_leave_status" class="form-control">
    <option value="Pending" selected>Pending</option>
    <option value="Approved">Approved</option>
  </select>
</div>


          <!-- Container for dynamic fields -->
          <div id="applyLeaveModalFields"></div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Submit Application</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

    <div id="leave_types" class="tab-content" style="display:none;">
        <h2>Leave Types</h2>
        <p>(Leave types management UI to be implemented here)</p>
    </div>

    <div id="reports" class="tab-content" style="display:none;">
        <h2>Reports</h2>
        <p>(Reports UI to be implemented here)</p>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<!-- Popper.js for Bootstrap 4 tooltips, popovers & modals -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="../js/hr_dashboard_script.js"></script>
<script src="../js/applyleave_script.js"></script>



</body>
<!-- View Leave Modal -->
<div class="modal fade" id="viewLeaveModal" tabindex="-1" role="dialog" aria-labelledby="viewLeaveModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewLeaveModalLabel">Leave Application Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
  <!-- Content will be injected via AJAX -->
</div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
</html>
