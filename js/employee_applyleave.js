// ==============================
// employee_applyleave.js
// ==============================

// Required fields per leave type (match your HR map)
const EMP_LEAVE_FIELDS = {
  "Vacation Leave": ["date_from", "date_to", "number_of_days", "place_spent"],
  "Sick Leave": ["date_from", "date_to", "number_of_days", "illness_details", "medical_certificate"],
  "Maternity Leave": ["date_from", "date_to", "number_of_days", "expected_delivery_date", "actual_delivery_date", "medical_certificate"],
  "Paternity Leave": ["date_from", "date_to", "number_of_days", "wife_name", "wife_delivery_date", "marriage_certificate", "birth_certificate"],
  "Study Leave": ["date_from", "date_to", "number_of_days", "purpose", "school_name", "admission_slip"],
  "Special Privilege Leave": ["date_from", "date_to", "number_of_days", "reason", "proof_if_required"],
  "Special Leave for Women": ["date_from", "date_to", "number_of_days", "gynecological_nature", "medical_certificate"],
  "Rehabilitation Leave": ["date_from", "date_to", "number_of_days", "cause_of_injury", "medical_certificate", "injury_report"],
  "Adoption Leave": ["date_from", "date_to", "number_of_days", "adoption_decree"],
  "Terminal Leave": ["effective_date", "number_of_days", "retirement_papers", "clearance"],
  "Compensatory Time-Off": ["cto_id", "date_from", "date_to", "number_of_days"] // special handling
};

// ---------- UI field builders ----------
function buildLabel(txt) { return txt.replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase()); }
function fText(name, required = true) {
  return `
    <div class="form-group">
      <label>${buildLabel(name)}</label>
      <input type="text" class="form-control" name="${name}" ${required ? "required" : ""}>
    </div>`;
}
function fDate(name, required = true) {
  return `
    <div class="form-group">
      <label>${buildLabel(name)}</label>
      <input type="date" class="form-control" name="${name}" ${required ? "required" : ""}>
    </div>`;
}
function fNumber(name = "number_of_days") {
  return `
    <div class="form-group">
      <label>Number of Days</label>
      <input type="number" step="0.01" min="0" class="form-control" name="${name}" required>
    </div>`;
}
function fFile(name) {
  return `
    <div class="form-group">
      <label>${buildLabel(name)}</label>
      <input type="file" class="form-control-file" name="${name}" required>
    </div>`;
}
function fieldFor(name) {
  switch (name) {
    case "date_from":
    case "date_to":
    case "effective_date":
    case "wife_delivery_date":
    case "expected_delivery_date":
    case "actual_delivery_date":
      return fDate(name);
    case "number_of_days":
      return fNumber("number_of_days");
    case "medical_certificate":
    case "marriage_certificate":
    case "birth_certificate":
    case "admission_slip":
    case "proof_if_required":
    case "injury_report":
    case "retirement_papers":
    case "clearance":
    case "adoption_decree":
      return fFile(name);
    default:
      return fText(name);
  }
}

// ---------- CTO select from preloaded data ----------
function buildCtoSelect(preselectId) {
  const list = Array.isArray(window.EMP_CTO) ? window.EMP_CTO : [];
  let html = `
    <div class="form-group">
      <label>Select CTO Credit</label>
      <select class="form-control" name="cto_id" required>
        <option value="">-- choose one --</option>`;
  list.forEach(c => {
    const bal = Number(c.balance || 0).toFixed(2);
    const sel = preselectId && String(preselectId) === String(c.cto_id) ? "selected" : "";
    html += `<option value="${c.cto_id}" ${sel}>${c.source} (Balance: ${bal} days)</option>`;
  });
  html += `</select></div>`;
  return html;
}

// ---------- Formatting helpers ----------
function fmtDate(d) {
  if (!d) return "";
  const dt = new Date(d);
  if (isNaN(dt.getTime())) return "";
  return dt.toLocaleDateString(undefined, { month: "short", day: "2-digit", year: "numeric" });
}
function buildDateRangeFromForm(formData) {
  const from = formData.get("date_from");
  const to   = formData.get("date_to");
  const eff  = formData.get("effective_date");
  if (from)  return to ? `${fmtDate(from)} – ${fmtDate(to)}` : fmtDate(from);
  if (eff)   return fmtDate(eff);
  return "—";
}

// Find the correct tbody for "My Leave Applications"
function getApplicationsTbody() {
  const $byId = $("#applicationsTableBody");
  if ($byId.length) return $byId;

  // Fallback: find the table right under the "My Leave Applications" section title
  const $section = $("h6.section-title").filter(function () {
    return $(this).text().trim().toLowerCase() === "my leave applications";
  }).first();

  const $tbodyViaSection = $section
    .nextAll(".table-responsive").first()
    .find("table tbody").first();

  if ($tbodyViaSection.length) return $tbodyViaSection;

  // Last fallback: first .table tbody on page (least preferred)
  return $("table.table tbody, table.table-sm tbody").first();
}

// ---------- Modal open with dynamic fields ----------
function openEmpApplyModal(employeeId, leaveTypeId, leaveTypeName, preselectCtoId) {
  $("#empApplyLeaveFields").empty();
  $("#emp_apply_employee_id").val(employeeId);
  $("#emp_apply_leave_type_id").val(leaveTypeId);
  $("#emp_apply_leave_type_name").val(leaveTypeName);

  if (parseInt(leaveTypeId, 10) === 12) {
    // CTO
    let html = buildCtoSelect(preselectCtoId);
    html += fDate("date_from");
    html += fDate("date_to");
    html += fNumber("number_of_days");
    $("#empApplyLeaveFields").html(html);
  } else {
    const fields = EMP_LEAVE_FIELDS[leaveTypeName] || [];
    if (!fields.includes("number_of_days")) fields.push("number_of_days");
    fields.forEach(f => $("#empApplyLeaveFields").append(fieldFor(f)));
  }

  $("#empApplyLeaveModal").modal("show");
}

// ---------- Box & button triggers ----------
$(document).on("click", ".leave-credit-box.clickable, .leave-credit-box .apply-btn", function (e) {
  e.preventDefault();
  const box = $(this).closest(".leave-credit-box");
  const employeeId   = box.data("employee-id");
  const leaveTypeId  = box.data("leave-type-id");
  const leaveTypeName= box.data("leave-type-name");
  const ctoId        = box.data("cto-id") || null;

  openEmpApplyModal(employeeId, leaveTypeId, leaveTypeName, ctoId);
});

// ---------- Submit form via AJAX, update table without reload ----------
$("#empApplyLeaveForm").on("submit", function (e) {
  e.preventDefault();
  const formData = new FormData(this);
  const $modal = $("#empApplyLeaveModal");
  const $tbody = getApplicationsTbody();

  $.ajax({
    url: "/depedlu_lms/users/employee_apply_leave.php",
    method: "POST",
    data: formData,
    contentType: false,
    processData: false,
    dataType: "json" // force JSON parsing
  }).done(res => {
    if (!res || res.success !== true) {
      alert((res && res.message) || "Failed to submit leave application.");
      return;
    }

    // Build row using response + form values (shows immediately)
    const app = res.application || {};
    const id = app.id ?? app.application_id ?? ""; // <-- requires backend to include it
    const leaveTypeName = $("#emp_apply_leave_type_name").val() || app.leave_type || "";
    const dates = buildDateRangeFromForm(formData) || app.dates || "—";
    const numDays = Number(formData.get("number_of_days") || app.number_of_days || 0).toFixed(2);
    const created = app.created_at || new Date().toLocaleDateString(undefined, { month: "short", day: "2-digit", year: "numeric" });
    const updated = app.updated_at || created;
    const approver = app.approver || "—";
    const statusText = app.status || "Pending";
    const badgeMap = { Approved: "success", Pending: "warning", Rejected: "danger", Cancelled: "dark" };
    const badge = badgeMap[statusText] || "secondary";

    // Remove "No applications yet." placeholder row if present
    $tbody.find("td.text-muted.text-center").closest("tr").remove();

    // Prepend new row
    const row = `
      <tr>
        <td>${id}</td>
        <td>${leaveTypeName}</td>
        <td>${dates}</td>
        <td>${numDays}</td>
        <td><span class="badge badge-${badge}">${statusText}</span></td>
        <td>${approver}</td>
        <td>${created}</td>
        <td>${updated}</td>
      </tr>`;
    $tbody.prepend(row);

    // Close modal, then reset/clear after it's fully hidden
    $modal.one("hidden.bs.modal", function () {
      $("#empApplyLeaveForm")[0].reset();
      $("#empApplyLeaveFields").empty();
      alert(res.message || "Leave application submitted.");
    });
    $modal.modal("hide");
  }).fail(xhr => {
    console.error("Employee Apply Leave error:", xhr.responseText);
    alert("Failed to submit leave application.");
  });
});
