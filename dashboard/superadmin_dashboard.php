<?php
session_start();
require_once '../config/database.php';

// Check if super admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: /depedlu_lms/auth/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Super Admin Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/depedlu_lms/css/superadmin_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body>

<button class="sidebar-toggle" onclick="toggleSidebar()">☰ Menu</button>

<div class="sidebar" id="sidebar">
    <h4 class="text-center">Super Admin</h4>
    <a href="#" onclick="showTab('home')">Dashboard Home</a>
    <a href="#" onclick="showTab('manage_admins')">Manage Admins</a>
    <a href="#" onclick="showTab('manage_hr')">Manage HR</a>
    <a href="/depedlu_lms/auth/logout.php">Logout</a>
</div>

<div class="content">
    <div id="home" class="tab-content">
        <h2>Welcome to Super Admin Dashboard</h2>
        <p>Overview of the LMS system for DepEd La Union.</p>
    </div>

    <!-- Manage Admins tab -->
    <div id="manage_admins" class="tab-content" style="display:none;">
        <h2>Manage Admins</h2>
        <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addAdminModal">Add New Admin</button>

        <div id="adminsTableContainer">
            <?php include '../users/get_admins_table.php'; ?>
        </div>
    </div>
        <!-- Manage hr tab -->
<div id="manage_hr" class="tab-content" style="display:none;">
    <h2>Manage HR</h2>
    <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addHRModal">Add New HR</button>

    <div id="hrTableContainer">
        <?php include '../users/get_hr_table.php'; ?>
    </div>
</div>
</div>



<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" role="dialog" aria-labelledby="addAdminModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="addAdminForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add New Admin</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Username:</label>
            <input type="text" class="form-control" name="new_username" required>
          </div>
          <div class="form-group">
            <label>Email:</label>
            <input type="email" class="form-control" name="new_email" required>
          </div>
          <div class="form-group">
            <label>Password:</label>
            <input type="password" class="form-control" name="new_password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Add Admin</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1" role="dialog" aria-labelledby="editAdminModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="editAdminForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Admin</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_user_id" id="edit_user_id">
          <div class="form-group">
            <label>Username:</label>
            <input type="text" class="form-control" name="edit_username" id="edit_username" required>
          </div>
          <div class="form-group">
            <label>Email:</label>
            <input type="email" class="form-control" name="edit_email" id="edit_email" required>
          </div>
          <div class="form-group">
            <label>New Password (leave blank to keep current):</label>
            <input type="password" class="form-control" name="edit_password" id="edit_password">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Update Admin</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-- Add HR Modal -->
<div class="modal fade" id="addHRModal" tabindex="-1" role="dialog" aria-labelledby="addHRModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="addHRForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add New HR</h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Username:</label>
            <input type="text" class="form-control" name="new_username" required>
          </div>
          <div class="form-group">
            <label>Email:</label>
            <input type="email" class="form-control" name="new_email" required>
          </div>
          <div class="form-group">
            <label>Password:</label>
            <input type="password" class="form-control" name="new_password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Add HR</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Edit Hr Modal -->
<!-- Edit HR Modal -->
<div class="modal fade" id="editHRModal" tabindex="-1" role="dialog" aria-labelledby="editHRModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="editHRForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit HR</h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_hr_id" id="edit_hr_id">
          
          <div class="form-group">
            <label>Username:</label>
            <input type="text" class="form-control" name="edit_hr_username" id="edit_hr_username" required>
          </div>
          
          <div class="form-group">
            <label>Email:</label>
            <input type="email" class="form-control" name="edit_hr_email" id="edit_hr_email" required>
          </div>
          
          <div class="form-group">
            <label>New Password (leave blank to keep current):</label>
            <input type="password" class="form-control" name="edit_hr_password" id="edit_hr_password">
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Update HR</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>


<script>
function showTab(tabId) {
    $(".tab-content").hide();
    $("#" + tabId).show();
    window.location.hash = tabId;
    if (window.innerWidth <= 768) {
        $("#sidebar").removeClass("show");
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
    $("#sidebar").toggleClass("show");
}

// Load data into Edit Admin modal
$(document).on('click', '.edit-btn', function(){
    $('#edit_user_id').val($(this).data('id'));
    $('#edit_username').val($(this).data('username'));
    $('#edit_email').val($(this).data('email'));
    $('#edit_password').val('');
    $('#editAdminModal').modal('show');
});

// Submit Add Admin form via AJAX
$("#addAdminForm").submit(function(e){
    e.preventDefault();
    $.ajax({
        url: '/depedlu_lms/users/add_admin.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response){
            alert(response.message);
            if(response.success){
                $('#addAdminModal').modal('hide');
                loadAdminsTable();
                $("#addAdminForm")[0].reset();
            }
        },
        error: function(){ alert('Error adding admin.'); }
    });
});

// Submit Edit Admin form via AJAX
$("#editAdminForm").submit(function(e){
    e.preventDefault();
    $.ajax({
        url: '/depedlu_lms/users/edit_admin.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response){
            alert(response.message);
            if(response.success){
                $('#editAdminModal').modal('hide');
                loadAdminsTable();
            }
        },
        error: function(){ alert('Error updating admin.'); }
    });
});
// Delete Admin via AJAX
$(document).on('click', '.delete-btn', function(){
    if(confirm("Are you sure you want to delete this admin?")){
        var id = $(this).data('id');

        $.ajax({
            url: '/depedlu_lms/users/delete_admin.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response){
                alert(response.message);
                if(response.success){
                    loadAdminsTable();
                }
            },
            error: function(){
                alert('Error deleting admin.');
            }
        });
    }
});

// Reload admins table
function loadAdminsTable(){
    $.ajax({
        url: '/depedlu_lms/users/get_admins_table.php',
        type: 'GET',
        success: function(data){
            $("#adminsTableContainer").html(data);
        },
        error: function(){ alert('Failed to load admins table.'); }
    });
}
// Add HR via AJAX
$("#addHRForm").submit(function(e){
    e.preventDefault();
    $.ajax({
        url: '/depedlu_lms/users/add_hr.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response){
            alert(response.message);
            if(response.success){
                $('#addHRModal').modal('hide');
                loadHRTable();
                $("#addHRForm")[0].reset();
            }
        },
        error: function(){ alert('Error adding HR.'); }
    });
});

// Load Edit HR modal data
$(document).on('click', '.edit-hr-btn', function(){
    $('#edit_hr_id').val($(this).data('id'));
    $('#edit_hr_username').val($(this).data('username'));
    $('#edit_hr_email').val($(this).data('email'));
    $('#editHRModal').modal('show');
});

// Submit Edit HR form via AJAX
$("#editHRForm").submit(function(e){
    e.preventDefault();
    $.ajax({
        url: '/depedlu_lms/users/edit_hr.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response){
            alert(response.message);
            if(response.success){
                $('#editHRModal').modal('hide');
                loadHRTable();
            }
        },
        error: function(){ alert('Error updating HR.'); }
    });
});

// Delete HR via AJAX
$(document).on('click', '.delete-hr-btn', function(){
    if(confirm("Are you sure you want to delete this HR?")){
        var id = $(this).data('id');
        $.ajax({
            url: '/depedlu_lms/users/delete_hr.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response){
                alert(response.message);
                if(response.success){
                    loadHRTable();
                }
            },
            error: function(){ alert('Error deleting HR.'); }
        });
    }
});

// Reload HR table
function loadHRTable(){
    $.ajax({
        url: '/depedlu_lms/users/get_hr_table.php',
        type: 'GET',
        success: function(data){
            $("#hrTableContainer").html(data);
        },
        error: function(){ alert('Failed to load HR table.'); }
    });
}

</script>

</body>
</html>
