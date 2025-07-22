// ------------ Tab Navigation ------------
function showTab(tabId) {
  const contents = document.getElementsByClassName("tab-content");
  for (let i = 0; i < contents.length; i++) {
    contents[i].style.display = "none";
  }
  document.getElementById(tabId).style.display = "block";

  if (tabId === "employees") {
    loadEmployeesTable();
    $("#employeeDetailsContainer").hide().empty();
    $("#employees h2, #employees > .btn-primary").show();
  }

  window.location.hash = tabId;
  if (
    tabId === "manage_leave" &&
    typeof loadLeaveApplicationsTable === "function"
  ) {
    const currentFilter = $("#filterStatus").val() || "";
    loadLeaveApplicationsTable(currentFilter);
  }

  if (window.innerWidth <= 768) {
    document.getElementById("sidebar").classList.remove("show");
  }
}

// ------------ Data Loaders ------------

// Load leave applications into Manage Leave
function loadLeaveApplicationsTable(status = "") {
  $.get(
    "/depedlu_lms/users/get_leave_applications_table.php",
    { status },
    function (data) {
      $("#leaveTable").html(data);
    }
  ).fail(() => alert("Failed to load leave applications."));
}

// Reload full employee details
function reloadEmployeeDetails(employeeId) {
  $.ajax({
    url: "/depedlu_lms/users/get_employee_details.php",
    method: "GET",
    data: { employee_id: employeeId },
    success: function (response) {
      $("#employeeDetailsContainer")
        .html(
          `
          <button id="backToEmployeeList" class="btn btn-secondary mb-3">← Back to Employee List</button>
          ${response}
        `
        )
        .show();
      $("#employeesTableContainer").hide();
      $("#employees h2, #employees > .btn-primary").hide();
      $("html, body").animate(
        { scrollTop: $("#employeeDetailsContainer").offset().top },
        500
      );
    },
    error: function () {
      alert("Failed to reload employee details.");
    },
  });
}

// Reload employees table
function loadEmployeesTable() {
  $.get("/depedlu_lms/users/get_employees_table.php", function (data) {
    $("#employeesTableContainer").html(data).show();
    $("#employeeDetailsContainer").hide().empty();
  }).fail(() => alert("Failed to load employees."));
}

// ------------ Initialization ------------
window.onload = function () {
  if (window.location.hash) {
    showTab(window.location.hash.substring(1));
  } else {
    showTab("home");
  }
};
console.log("hr_manage.js loaded and ready");

$(document).ready(function () {
  // ---- Employee modals & table handlers ----

  // Open Edit Employee modal
  $(document).on("click", ".edit-btn", function () {
    const data = $(this).data();
    $("#edit_employee_id").val(data.id);
    $("#edit_employee_number").val(data.employee_number);
    $("#edit_first_name").val(data.first_name);
    $("#edit_middle_name").val(data.middle_name);
    $("#edit_last_name").val(data.last_name);
    $("#edit_employment_type").val(data.employment_type);
    $("#edit_position").val(data.position);
    $("#edit_office").val(data.office);
    $("#edit_email").val(data.email);
    $("#edit_date_hired").val(data.date_hired);
    $("#edit_status").val(data.status);
    $("#edit_password").val("");
    $("#editEmployeeModal").modal("show");
  });

  // Triple-click to enable employee number editing
  let clickCount = 0,
    clickTimer;
  $("#edit_employee_number")
    .on("click", function () {
      clickCount++;
      if (clickCount === 3) {
        $(this).prop("readonly", false).focus();
        clickCount = 0;
        clearTimeout(clickTimer);
      } else {
        clearTimeout(clickTimer);
        clickTimer = setTimeout(() => {
          clickCount = 0;
        }, 600);
      }
    })
    .on("blur", function () {
      $(this).prop("readonly", true);
    });

  // Add Employee
  $("#addEmployeeForm").submit(function (e) {
    e.preventDefault();
    $.post(
      "/depedlu_lms/users/add_employee.php",
      $(this).serialize(),
      function (response) {
        alert(response.message);
        if (response.success) {
          $("#addEmployeeModal").modal("hide");
          loadEmployeesTable();
        }
      },
      "json"
    ).fail(() => alert("Error adding employee."));
  });

  //reset addemployee modal when add employee button is clicked
  $("#addEmployeeModal").on("show.bs.modal", function () {
    // Reset native form fields
    $(this).find("form")[0].reset();
    // If you have any custom-styled selects or other widgets, you can reset them here too.
  });

  // Edit Employee
  $("#editEmployeeForm").submit(function (e) {
    e.preventDefault();
    $.post(
      "/depedlu_lms/users/edit_employee.php",
      $(this).serialize(),
      function (response) {
        alert(response.message);
        if (response.success) {
          $("#editEmployeeModal").modal("hide");
          const empId = $("#edit_employee_id").val();
          if (empId) reloadEmployeeDetails(empId);
        }
      },
      "json"
    ).fail(() => alert("Error updating employee."));
  });

  // Delete Employee
  $(document).on("click", ".delete-btn", function () {
    $("#employees h2, #employees > .btn-primary").show();
    if (confirm("Are you sure you want to delete this employee?")) {
      const id = $(this).data("id");
      $.post(
        "/depedlu_lms/users/delete_employee.php",
        { employee_id: id },
        function (response) {
          alert(response.message);
          if (response.success) loadEmployeesTable();
        },
        "json"
      ).fail(() => alert("Error deleting employee."));
    }
  });

  // View Employee Details
  $(document).on("click", ".view-employee-btn", function () {
    const employeeId = $(this).data("id");
    if (employeeId) reloadEmployeeDetails(employeeId);
  });

  // Back to list
  $(document).on("click", "#backToEmployeeList", function () {
    $("#employeeDetailsContainer").hide().empty();
    $("#employeesTableContainer").show();
    $("#employees h2, #employees > .btn-primary").show();
  });

  // ---- Leave Credit & Application Handlers ----

  // Open Leave Credit Action modal
  // Handler for clicking a leave-credit box
  $(document).on("click", ".leave-credit-box.clickable", function () {
    const $box = $(this);

    // 1) Grab the leave-type info from the box you clicked
    const leaveTypeName = $box.attr("data-leave-type");
    const leaveTypeId = $box.attr("data-leave-type-id");
    const employeeId = $box.attr("data-employee-id");

    // 2) Reset the Add Leave Credit form completely
    const $form = $("#addLeaveCreditForm");
    $form[0].reset();

    // 3) Pre-fill the fields we want to keep:
    $("#credit_employee_id").val(employeeId);
    $("#credit_leave_type_id").val(leaveTypeId);
    $("#credit_leave_type_name").val(leaveTypeName);

    // 4) Show the modal
    $("#leaveCreditActionModal").modal("hide"); // in case it's open
    $("#addLeaveCreditModal").modal("show");
  });

  // Submit Add Credit
  $("#addLeaveCreditForm").submit(function (e) {
    e.preventDefault();
    $.post(
      "/depedlu_lms/users/add_leave_credit.php",
      $(this).serialize(),
      function (response) {
        alert(response.message);
        if (response.success) {
          reloadEmployeeDetails($("#credit_employee_id").val());
        }
      },
      "json"
    ).fail(() => alert("Error adding leave credit."));
  });

  // ------------ Manage Leave Filter ------------
  $("#filterStatus").on("change", function () {
    loadLeaveApplicationsTable($(this).val());
  });

  // Initial load for Manage Leave
  if (window.location.hash === "#manage_leave") {
    loadLeaveApplicationsTable($("#filterStatus").val());
  }

  // ------------------------------
  // Manage Leave: View Application
  // ------------------------------
  // ------------------------------
  // Manage Leave: View Application
  // ------------------------------
  $(document).on("click", ".view-application-btn", function () {
    const appId = $(this).data("application-id");

    // Clear previous content & show loading state
    const $modalBody = $("#viewLeaveModal .modal-body");
    $modalBody.html("<p>Loading...</p>");

    // Fetch details
    $.get("/depedlu_lms/users/get_leave_application_details.php", {
      application_id: appId,
    })
      .done(function (html) {
        $modalBody.html(html);
      })
      .fail(function () {
        $modalBody.html(
          "<p class='text-danger'>Failed to load details. Please try again.</p>"
        );
      })
      .always(function () {
        $("#viewLeaveModal").modal("show");
      });
  });

  // Handle the edit form submission
  $(document).on("submit", "#editLeaveApplicationForm", function (e) {
    e.preventDefault();
    const $form = $(this);
    const payload = $form.serialize();

    $.post(
      "/depedlu_lms/users/update_leave_application.php",
      payload,
      function (res) {
        if (res.success) {
          $("#viewLeaveModal").modal("hide");
          // reload just the leave applications table with current filter
          const current = $("#filterStatus").val() || "";
          loadLeaveApplicationsTable(current);
          alert("Application updated.");
        } else {
          alert(res.message || "Update failed.");
        }
      },
      "json"
    ).fail(() => alert("Error saving changes."));
  });

  // 1) Open initial setup modal
  $(document).on("click", "#initialSetupLeaveBtn", function () {
    const empId = $("#setup_employee_id").val();
    $("#leaveTypeSelect").empty().append("<option>Loading…</option>");
    $("#initialCredits").val("");
    $("#initialSetupLeaveModal").modal("show");

    // fetch available types
    $.getJSON("/depedlu_lms/users/get_available_leave_types.php", {
      employee_id: empId,
    })
      .done((types) => {
        const sel = $("#leaveTypeSelect").empty();
        if (types.length === 0) {
          sel.append("<option disabled>No types left to assign</option>");
        } else {
          sel.append('<option value="">Select a leave type…</option>');
          types.forEach((t) => {
            sel.append(`<option value="${t.leave_type_id}">${t.name}</option>`);
          });
        }
      })
      .fail(() => {
        alert("Could not load leave types.");
      });
  });

  // 2) On “Next” button click → show confirmation
  $(document).on("click", "#initialSetupNextBtn", function (e) {
    const empId = $("#setup_employee_id").val();
    const typeId = $("#leaveTypeSelect").val();
    const typeName = $("#leaveTypeSelect option:selected").text();
    const credits = $("#initialCredits").val();

    if (!typeId || !credits) {
      return alert(
        "Please choose a leave type and enter an initial credit amount."
      );
    }

    $("#confirm_employee_id").val(empId);
    $("#confirm_leave_type_id").val(typeId);
    $("#confirm_total_credits").val(credits);
    $("#confirmTypeName").text(typeName);
    $("#confirmCredits").text(credits);

    $("#initialSetupLeaveModal").modal("hide");
    $("#confirmSetupModal").modal("show");
  });

  // 3) On confirm → POST to setup endpoint, then reload details
  $(document).on("click", "#doSetupBtn", function () {
    const data = {
      employee_id: $("#confirm_employee_id").val(),
      leave_type_id: $("#confirm_leave_type_id").val(),
      total_credits: $("#confirm_total_credits").val(),
      reason: "Initial setup",
    };

    console.log("Sending setup:", data);
    $.post(
      "/depedlu_lms/users/setup_leave_credit.php",
      data,
      (res) => {
        console.log("Setup response:", res);
        alert(res.message || (res.success ? "Setup complete!" : "Failed."));
        if (res.success) {
          // Hide and clean up
          $("#confirmSetupModal").modal("hide");
          $("body").removeClass("modal-open");
          $(".modal-backdrop").remove();

          // Reload the details panel
          reloadEmployeeDetails(data.employee_id);
        }
      },
      "json"
    ).fail((xhr, status, err) => {
      console.error("Error in setup call:", status, err, xhr.responseText);
      alert("Error saving setup. Check console for details.");
    });
  });

  // 4) Global cleanup whenever confirm modal is hidden
  $("#confirmSetupModal").on("hidden.bs.modal", function () {
    $("body").removeClass("modal-open");
    $(".modal-backdrop").remove();
  });
}); // end of $(document).ready()

// Sidebar toggle
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("show");
}
