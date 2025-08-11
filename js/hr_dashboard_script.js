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
function loadLeaveApplicationsTable(status = "") {
  $.get(
    "/depedlu_lms/users/get_leave_applications_table.php",
    { status },
    function (data) {
      $("#leaveTable").html(data);
    }
  ).fail(() => alert("Failed to load leave applications."));
}

function reloadEmployeeDetails(employeeId) {
  $.ajax({
    url: "/depedlu_lms/users/get_employee_details.php",
    method: "GET",
    data: { employee_id: employeeId },
    success: function (response) {
      $("#employeeDetailsContainer")
        .html(
          `<button id="backToEmployeeList" class="btn btn-secondary mb-3">← Back to Employee List</button>
           ${response}`
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
console.log("hr_dashboard_script.js loaded and ready");

$(document).ready(function () {
  // Cache original action-modal body so we can restore it
  const originalLeaveCreditBody = $(
    "#leaveCreditActionModal .modal-body"
  ).html();

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

  $("#addEmployeeModal").on("show.bs.modal", function () {
    $(this).find("form")[0].reset();
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
          reloadEmployeeDetails($("#edit_employee_id").val());
        }
      },
      "json"
    ).fail(() => alert("Error updating employee."));
  });

  // Delete Employee
  $(document).on("click", ".delete-btn", function () {
    $("#employees h2, #employees > .btn-primary").show();
    if (!confirm("Are you sure you want to delete this employee?")) return;
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
  });

  // View Employee Details
  $(document).on("click", ".view-employee-btn", function () {
    reloadEmployeeDetails($(this).data("id"));
  });

  // Back to list
  $(document).on("click", "#backToEmployeeList", function () {
    $("#employeeDetailsContainer").hide().empty();
    $("#employeesTableContainer").show();
    $("#employees h2, #employees > .btn-primary").show();
  });

  // ---- Leave Credit & Application Handlers ----

  // Open Leave Credit Action modal
  $(document).on("click", ".leave-credit-box.clickable", function () {
    // restore original two-button body
    $("#leaveCreditActionModal .modal-body").html(originalLeaveCreditBody);

    const $box = $(this);
    const leaveTypeId = $box.data("leave-type-id");
    const leaveType = $box.data("leave-type");
    const creditId = $box.data("credit-id");
    const empId = $box.data("employee-id");

    $("#modalEmployeeId").val(empId);
    $("#modalLeaveType").val(leaveTypeId);
    $("#modalCreditId").val(creditId);
    $("#leaveTypeName").text(leaveType);

    $("#leaveCreditActionModal").modal("show");
  });

  // Manage Leave Filter
  $("#filterStatus").on("change", function () {
    loadLeaveApplicationsTable($(this).val());
  });
  if (window.location.hash === "#manage_leave") {
    loadLeaveApplicationsTable($("#filterStatus").val());
  }

  // View Leave Application
  $(document).on("click", ".view-application-btn", function () {
    const appId = $(this).data("application-id");
    const $mb = $("#viewLeaveModal .modal-body").html("<p>Loading...</p>");
    $.get("/depedlu_lms/users/get_leave_application_details.php", {
      application_id: appId,
    })
      .done((html) => $mb.html(html))
      .fail(() =>
        $mb.html("<p class='text-danger'>Failed to load details.</p>")
      )
      .always(() => $("#viewLeaveModal").modal("show"));
  });

  // Submit edit leave application
  $(document).on("submit", "#editLeaveApplicationForm", function (e) {
    e.preventDefault();
    $.post(
      "/depedlu_lms/users/update_leave_application.php",
      $(this).serialize(),
      (res) => {
        if (res.success) {
          $("#viewLeaveModal").modal("hide");
          loadLeaveApplicationsTable($("#filterStatus").val() || "");
          alert("Application updated.");
        } else alert(res.message || "Update failed.");
      },
      "json"
    ).fail(() => alert("Error saving changes."));
  });

  // ------------------------
  // 1) Open initial setup modal
  // ------------------------
  $(document).on("click", "#initialSetupLeaveBtn", function () {
    const empId = $("#setup_employee_id").val();
    const $select = $("#leaveTypeSelect")
      .empty()
      .append("<option>Loading…</option>");
    $("#initialCredits").val("");
    $("#ctoFieldsContainer").empty();
    $("#initialCreditsContainer").show();
    $("#initialSetupLeaveModal").modal("show");

    $.getJSON("/depedlu_lms/users/get_available_leave_types.php", {
      employee_id: empId,
    })
      .done((res) => {
        // Check success flag
        if (!res.success) {
          return alert(res.message || "Could not load leave types.");
        }

        const types = Array.isArray(res.data) ? res.data : [];
        $select.empty();

        if (!types.length) {
          $select.append("<option disabled>No types left to assign</option>");
        } else {
          $select.append('<option value="">Select a leave type…</option>');
          types.forEach((t) =>
            $select.append(
              `<option value="${t.leave_type_id}">${t.name}</option>`
            )
          );
        }
      })
      .fail(() => alert("Could not load leave types."));
  });

  // ------------------------
  // 1a) Inject Add Leave Credit mini-form
  // ------------------------
  $(document).on("click", "#addLeaveCreditBtn", function () {
    const empId = $("#modalEmployeeId").val();
    const leaveTypeId = $("#modalLeaveType").val();
    const leaveType = $("#leaveTypeName").text().trim();
    const formHtml = `
      <h5 class="mb-3">Add Leave Credit: ${leaveType}</h5>
      <input type="hidden" id="add_credit_employee_id" value="${empId}">
      <input type="hidden" id="add_credit_leave_type_id" value="${leaveTypeId}">
      <form id="addCreditForm">
        <div class="form-group">
          <label>Number of Days</label>
          <input type="number" step="0.01" min="0" class="form-control" name="total_credits" required>
        </div>
        <div class="form-group">
          <label>Reason</label>
          <textarea class="form-control" name="reason" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
      </form>
    `;
    $("#leaveCreditActionModal .modal-body").html(formHtml);
  });

  // ------------------------
  // 1b) Handle Add Credit form submit
  // ------------------------
  $(document).on("submit", "#addCreditForm", function (e) {
    e.preventDefault();
    const empId = $("#add_credit_employee_id").val();
    const leaveTypeId = $("#add_credit_leave_type_id").val();
    const total = $(this).find('input[name="total_credits"]').val();
    const reason = $(this).find('textarea[name="reason"]').val();
    $.post(
      "/depedlu_lms/users/add_leave_credit.php",
      {
        employee_id: empId,
        leave_type_id: leaveTypeId,
        total_credits: total,
        reason: reason,
      },
      function (res) {
        alert(res.message);
        if (res.success) {
          $("#leaveCreditActionModal").modal("hide");
          $("body").removeClass("modal-open");
          $(".modal-backdrop").remove();
          reloadEmployeeDetails(empId);
        }
      },
      "json"
    ).fail(() => alert("Error adding leave credit."));
  });

  // ------------------------
  // 2) Inject CTO fields on change
  // ------------------------
  $(document).on("change", "#leaveTypeSelect", function () {
    if (this.value === "12") {
      $("#initialCreditsContainer").hide();
      $("#ctoFieldsContainer").html(`
        <div class="form-group"><label>Source</label><input type="text" class="form-control" id="source" required></div>
        <div class="form-group"><label>Earned At</label><input type="date" class="form-control" id="earned_at" required></div>
        <div class="form-group"><label>Expires At</label><input type="date" class="form-control" id="expires_at" required></div>
        <div class="form-group"><label>Number of Days</label><input type="number" step="0.01" min="0" class="form-control" id="number_of_days" required></div>`);
    } else {
      $("#initialCreditsContainer").show();
      $("#ctoFieldsContainer").empty();
    }
  });

  // ------------------------
  // 3) Handle Next button click
  // ------------------------
  $(document).on("click", "#initialSetupNextBtn", function () {
    const empId = $("#setup_employee_id").val();
    const typeId = $("#leaveTypeSelect").val();
    if (!typeId) return alert("Please select a leave type.");
    if (typeId === "12") {
      // CTO path
      const src = $("#source").val().trim();
      const ea = $("#earned_at").val();
      const ex = $("#expires_at").val();
      const days = $("#number_of_days").val();
      if (!src || !ea || !ex || !days) return alert("Fill all CTO fields.");
      $.post(
        "/depedlu_lms/users/setup_leave_credit.php",
        {
          employee_id: empId,
          leave_type_id: typeId,
          source: src,
          earned_at: ea,
          expires_at: ex,
          number_of_days: days,
        },
        (res) => {
          if (res.success) {
            alert(res.message);
            $("#initialSetupLeaveModal").modal("hide");
            $("body").removeClass("modal-open");
            $(".modal-backdrop").remove();
            $("#initialSetupLeaveForm")[0].reset();
            $("#ctoFieldsContainer").empty();
            $("#initialCreditsContainer").show();
            reloadEmployeeDetails(empId);
          } else {
            console.error("CTO Error:", res.debug);
            alert(res.message);
          }
        },
        "json"
      ).fail(() => alert("Error adding CTO credit."));
    } else {
      // Non‑CTO flow
      const credits = $("#initialCredits").val();
      if (!credits) return alert("Enter initial credit amount.");
      $("#confirm_employee_id").val(empId);
      $("#confirm_leave_type_id").val(typeId);
      $("#confirm_total_credits").val(credits);
      $("#confirmTypeName").text($("#leaveTypeSelect option:selected").text());
      $("#confirmCredits").text(credits);
      $("#initialSetupLeaveModal").modal("hide");
      $("#confirmSetupModal").modal("show");
    }
  });

  // ------------------------
  // 4) Confirm Setup click
  // ------------------------
  $(document).on("click", "#doSetupBtn", function () {
    const data = {
      employee_id: $("#confirm_employee_id").val(),
      leave_type_id: $("#confirm_leave_type_id").val(),
      total_credits: $("#confirm_total_credits").val(),
      reason: "Initial setup",
    };
    $.post(
      "/depedlu_lms/users/setup_leave_credit.php",
      data,
      (res) => {
        alert(res.message || (res.success ? "Setup complete!" : "Failed."));
        if (res.success) {
          $("#confirmSetupModal").modal("hide");
          $("body").removeClass("modal-open");
          $(".modal-backdrop").remove();
          reloadEmployeeDetails(data.employee_id);
        }
      },
      "json"
    ).fail(() => alert("Error saving setup."));
  });

  // ------------------------
  // 5) Cleanup on modal hide
  // ------------------------
  $("#initialSetupLeaveModal").on("hidden.bs.modal", function () {
    this.querySelector("form").reset();
    $("#ctoFieldsContainer").empty();
    $("#initialCreditsContainer").show();
    $("#leaveTypeSelect").val("").trigger("change");
  });
  $("#confirmSetupModal").on("hidden.bs.modal", function () {
    $(
      "#confirm_employee_id, #confirm_leave_type_id, #confirm_total_credits"
    ).val("");
  });
  $("#leaveCreditActionModal").on("hidden.bs.modal", function () {
    const f = this.querySelector("form");
    if (f) f.reset();
  });
  $("#applyLeaveModal").on("hidden.bs.modal", function () {
    this.querySelector("form").reset();
    $("#applyLeaveModalFields").empty();
  });

  // ─── 1) Open the “Deduct Credit” modal ───────────────────────────────────────────
  $(document).on("click", "#deductLeaveCreditBtn", function () {
    // Get current context from the action modal
    const leaveTypeName = $("#leaveTypeName").text().trim();
    const empId = $("#modalEmployeeId").val();
    const leaveTypeId = $("#modalLeaveType").val();
    const creditId = $("#modalCreditId").val();

    // Set modal title and hidden inputs
    $("#deductLeaveCreditModalLabel").text(
      `Deduct Leave Credit: ${leaveTypeName}`
    );
    $("#deduct_employee_id").val(empId);
    $("#deduct_leave_type_id").val(leaveTypeId);
    $("#deduct_credit_id").val(creditId);

    // Show the Deduct modal
    $("#leaveCreditActionModal").modal("hide");
    $("#deductLeaveCreditModal").modal("show");
  });

  // ─── 2) Submit the “Deduct Credit” form via AJAX ─────────────────────────────────
  $(document).on("submit", "#deductLeaveCreditForm", function (e) {
    e.preventDefault();

    $.ajax({
      url: "/depedlu_lms/users/deduct_leave_credit.php",
      method: "POST",
      data: $(this).serialize(),
      dataType: "json",
      success: function (res) {
        alert(res.message);
        if (res.success) {
          // Close modal & cleanup backdrop
          $("#deductLeaveCreditModal").modal("hide");
          $("body").removeClass("modal-open");
          $(".modal-backdrop").remove();
          // Refresh employee details to show updated balance
          const empId = $("#deduct_employee_id").val();
          if (empId) reloadEmployeeDetails(empId);
        }
      },
      error: function (xhr, status, error) {
        console.error("Deduct AJAX Error:", status, error);
        alert("Failed to deduct credit.");
      },
    });
  });

  // ─── Setup‐Credit “+” Box Handler ────────────────────────────────────────────
  $(document).on("click", ".setup-leave-box", function () {
    const empId = $(this).data("employee-id");
    const typeId = $(this).data("leave-type-id"); // "" or 12
    const $modal = $("#initialSetupLeaveModal");
    const $select = $("#leaveTypeSelect");

    // 1) Set employee
    $("#setup_employee_id").val(empId);

    // 2) If CTO (+ on the CTO section), force the CTO option
    if (String(typeId) === "12") {
      // Replace dropdown with only CTO option, then trigger change
      $select.html('<option value="12">Compensatory Time-Off</option>');
      // Hide the "Initial Credits" container so CTO fields show
      $("#initialCreditsContainer").hide();
      // Manually inject the CTO fields (source/earned_at/expires_at/number_of_days)
      $("#ctoFieldsContainer").html(`
      <div class="form-group"><label>Source</label><input type="text" class="form-control" name="source" required></div>
      <div class="form-group"><label>Earned At</label><input type="date" class="form-control" name="earned_at" required></div>
      <div class="form-group"><label>Expires At</label><input type="date" class="form-control" name="expires_at" required></div>
      <div class="form-group"><label>Number of Days</label><input type="number" step="0.01" min="0" class="form-control" name="total_credits" required></div>
    `);
    } else {
      // Regular leave‐type flow: clear CTO fields, refill dropdown via AJAX
      $("#ctoFieldsContainer").empty();
      $("#initialCreditsContainer").show();
      $select.val("").trigger("change");
    }

    // 3) Show the modal
    $modal.modal("show");
  });
}); // end of $(document).ready()

// Sidebar toggle
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("show");
}
