// Mapping of leave types to their required form fields
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

// Utility to build a form field HTML snippet based on its name
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

// Utility to build a date input (used for CTO)
function buildDateField(name) {
  const label = name
    .replace(/_/g, " ")
    .replace(/\b\w/g, (l) => l.toUpperCase());
  return `
    <div class="form-group">
      <label>${label}</label>
      <input type="date" class="form-control" name="${name}" required>
    </div>`;
}

// Utility to build a number input (used for CTO)
function buildNumberField(name) {
  return `
    <div class="form-group">
      <label>Number of Days</label>
      <input type="number" step="0.01" min="0" class="form-control" name="${name}" required>
    </div>`;
}

// Builds and shows the Apply Leave modal, branching for CTO vs. others
function openApplyLeaveModal(employeeId, leaveTypeName, leaveTypeId) {
  // Clear previous fields
  $("#applyLeaveModalFields").empty();
  $("#apply_leave_employee_id").val(employeeId);
  $("#apply_leave_type_id").val(leaveTypeId);
  $("#apply_leave_type_name").val(leaveTypeName);

  // CTO case
  if (parseInt(leaveTypeId, 10) === 12) {
    $.getJSON("/depedlu_lms/users/get_cto_earnings.php", {
      employee_id: employeeId,
    })
      .done((res) => {
        // Determine where the array lives
        let ctos = [];
        if (res && Array.isArray(res.data)) {
          ctos = res.data;
        } else if (Array.isArray(res)) {
          ctos = res;
        } else {
          console.error("Unexpected CTO payload:", res);
          alert(res.message || "Failed to load CTO credits.");
          return;
        }

        if (ctos.length === 0) {
          alert("No CTO credits available to apply.");
          return;
        }

        // Build the select + fields
        let html = `
          <div class="form-group">
            <label>Select CTO Credit</label>
            <select class="form-control" name="cto_id" required>
              <option value="">-- choose one --</option>`;
        ctos.forEach((c) => {
          const balance = (c.days_earned - c.days_used).toFixed(2);
          html += `<option value="${c.cto_id}">${c.source} (Balance: ${balance} days)</option>`;
        });
        html += `</select></div>`;
        html += buildDateField("date_from");
        html += buildDateField("date_to");
        html += buildNumberField("number_of_days");

        $("#applyLeaveModalFields").html(html);
        $("#leaveCreditActionModal").modal("hide");
        $("#applyLeaveModal").modal("show");
      })
      .fail(() => {
        alert("Could not load Compensatory Time-Off credits.");
      });
  }
  // Non-CTO case
  else {
    const fields = leaveTypeFieldsMap[leaveTypeName] || [];
    fields.forEach((field) => {
      $("#applyLeaveModalFields").append(buildField(field));
    });
    $("#leaveCreditActionModal").modal("hide");
    $("#applyLeaveModal").modal("show");
  }
}

// Handler for the “Apply Leave” button in the action modal
$(document).on("click", "#applyLeaveBtn", function () {
  const employeeId = $("#modalEmployeeId").val();
  const leaveTypeId = $("#modalLeaveType").val();
  const leaveTypeName = $("#leaveTypeName").text().trim();
  openApplyLeaveModal(employeeId, leaveTypeName, leaveTypeId);
});

// Submit the Apply Leave form via AJAX
$("#applyLeaveForm").submit(function (e) {
  e.preventDefault();
  const formData = new FormData(this);

  $.ajax({
    url: "/depedlu_lms/users/apply_leave.php",
    method: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (res) {
      if (!res.success) console.warn("Apply Leave debug:", res);
      alert(res.message);
      if (res.success) {
        $("#applyLeaveForm")[0].reset();
        $("#applyLeaveModalFields").empty();
        $("#applyLeaveModal").modal("hide");
        $("body").removeClass("modal-open");
        $(".modal-backdrop").remove();
        const empId = formData.get("employee_id");
        if (empId) reloadEmployeeDetails(empId);
        if ($("#manage_leave").is(":visible")) {
          loadLeaveApplicationsTable($("#filterStatus").val());
        }
      }
    },
    error: function (xhr) {
      console.error("Apply Leave AJAX Error:", xhr.responseText);
      alert("Failed to submit leave application.");
    },
  });
});
