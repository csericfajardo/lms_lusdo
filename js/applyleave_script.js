const leaveTypeFieldsMap = {
  "Vacation Leave": ["date_from", "date_to", "number_of_days", "place_spent"],
  "Sick Leave": [
    "date_from",
    "date_to",
    "number_of_days",
    "illness_details",
    "medical_certificate",
  ],
  "Maternity Leave": [
    "date_from",
    "date_to",
    "number_of_days",
    "expected_delivery_date",
    "actual_delivery_date",
    "medical_certificate",
  ],
  "Paternity Leave": [
    "date_from",
    "date_to",
    "number_of_days",
    "wife_name",
    "wife_delivery_date",
    "marriage_certificate",
    "birth_certificate",
  ],
  "Study Leave": [
    "date_from",
    "date_to",
    "number_of_days",
    "purpose",
    "school_name",
    "admission_slip",
  ],
  "Special Privilege Leave": [
    "date_from",
    "date_to",
    "number_of_days",
    "reason",
    "proof_if_required",
  ],
  "Special Leave for Women": [
    "date_from",
    "date_to",
    "number_of_days",
    "gynecological_nature",
    "medical_certificate",
  ],
  "Rehabilitation Leave": [
    "date_from",
    "date_to",
    "number_of_days",
    "cause_of_injury",
    "medical_certificate",
    "injury_report",
  ],
  "Adoption Leave": [
    "date_from",
    "date_to",
    "number_of_days",
    "adoption_decree",
  ],
  "Terminal Leave": [
    "effective_date",
    "number_of_days",
    "retirement_papers",
    "clearance",
  ],
};

// Utility to build fields
function buildField(field) {
  const label = field
    .replace(/_/g, " ")
    .replace(/\b\w/g, (l) => l.toUpperCase());

  switch (field) {
    case "date_from":
    case "date_to":
    case "effective_date":
    case "wife_delivery_date":
    case "expected_delivery_date":
    case "actual_delivery_date":
      return `
        <div class="form-group">
          <label>${label}</label>
          <input type="date" class="form-control" name="${field}" required>
        </div>`;
    case "number_of_days":
      return `
        <div class="form-group">
          <label>Number of Days</label>
          <input type="number" step="0.01" min="0" class="form-control" name="number_of_days" required>
        </div>`;
    case "medical_certificate":
    case "marriage_certificate":
    case "birth_certificate":
    case "admission_slip":
    case "proof_if_required":
    case "injury_report":
    case "retirement_papers":
    case "clearance":
    case "adoption_decree":
      return `
        <div class="form-group">
          <label>${label}</label>
          <input type="file" class="form-control-file" name="${field}" required>
        </div>`;
    default:
      return `
        <div class="form-group">
          <label>${label}</label>
          <input type="text" class="form-control" name="${field}" required>
        </div>`;
  }
}

// Show modal and dynamically build fields
function openApplyLeaveModal(employeeId, leaveTypeName, leaveTypeId) {
  console.log(
    "Opening modal for leave type:",
    leaveTypeName,
    "ID:",
    leaveTypeId
  );

  const fields = leaveTypeFieldsMap[leaveTypeName] || [];
  $("#applyLeaveModalFields").html(""); // Clear previous fields

  fields.forEach((field) => {
    const fieldHtml = buildField(field);
    $("#applyLeaveModalFields").append(fieldHtml);
  });

  // Set hidden values
  $("#apply_leave_employee_id").val(employeeId);
  $("#apply_leave_type_id").val(leaveTypeId);
  $("#apply_leave_type_name").val(leaveTypeName);

  // Reset status to Pending
  $("#apply_leave_status").val("Pending");

  $("#leaveCreditActionModal").modal("hide");
  $("#applyLeaveModal").modal("show");
}

// Button trigger from leave credit box
$(document).on("click", "#applyLeaveBtn", function () {
  const employeeId = $("#modalEmployeeId").val();
  const leaveTypeName = $("#modalLeaveType").val();

  const creditBox = $(".leave-credit-box").filter(function () {
    return (
      $(this).data("leave-type") === leaveTypeName &&
      $(this).data("employee-id") == employeeId
    );
  });

  const leaveTypeId = creditBox.data("leave-type-id");
  openApplyLeaveModal(employeeId, leaveTypeName, leaveTypeId);
});

// Submit form via AJAX
// Submit form via AJAX
$("#applyLeaveForm").submit(function (e) {
  e.preventDefault();

  const form = $("#applyLeaveForm")[0];
  const formData = new FormData(form);

  // DEBUG: log each field
  console.log("Submitting form with the following data:");
  for (let [key, value] of formData.entries()) {
    console.log(`${key}:`, value);
  }

  $.ajax({
    url: "/depedlu_lms/users/apply_leave.php",
    method: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (response) {
      console.log("Server response:", response);

      if (!response.success && response.debug) {
        console.warn("Debug Info from PHP:", response.debug);
      }

      alert(response.message);
      if (response.success) {
        $("#applyLeaveForm")[0].reset();
        $("#applyLeaveModalFields").html("");
        $("#applyLeaveModal").modal("hide");

        const employeeId = $("#apply_leave_employee_id").val();
        if (employeeId) {
          $.ajax({
            url: "/depedlu_lms/users/get_employee_details.php",
            method: "GET",
            data: { employee_id: employeeId },
            success: function (detailsHtml) {
              $("#employeeDetailsContainer")
                .html(
                  `
                  <button id="backToEmployeeList" class="btn btn-secondary mb-3">← Back to Employee List</button>
                  ${detailsHtml}
                `
                )
                .show();
              $("#employeesTableContainer").hide();
              $("#employees h2, #employees > .btn-primary").hide();
            },
          });
        }

        if (
          $("#manage_leave").is(":visible") &&
          typeof loadLeaveApplicationsTable === "function"
        ) {
          const currentFilter = $("#filterStatus").val();
          loadLeaveApplicationsTable(currentFilter);
        }
      }
    },
    error: function (xhr) {
      console.error("AJAX Error:", xhr.responseText);
      alert("Failed to submit leave application.");
    },
  });
});
