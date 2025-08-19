// admin_dashboard_script.js
// Requires jQuery + Bootstrap

(function ($) {
  const endpoints = {
    addHR:    "/depedlu_lms/users/add_hr.php",
    updateHR: "/depedlu_lms/users/edit_hr.php",
    deleteHR: "/depedlu_lms/users/delete_hr.php",
    hrTable:  "/depedlu_lms/users/get_hr_table.php",
  };

  const selectors = {
    form:   "#addHRForm",        // reused for Add + Edit
    modal:  "#addHRModal",
    btn:    '#addHRForm button[type="submit"]',
    title:  "#addHRModal .modal-title",
    table:  "#hrTable",
    fb:     "#addHrFeedback",
    i: {
      user_id:         'input[name="user_id"]',
      username:        'input[name="username"]',
      email:           'input[name="email"]',
      password:        'input[name="password"]',
      employee_number: 'input[name="employee_number"]',
      first_name:      'input[name="first_name"]',
      middle_name:     'input[name="middle_name"]',
      last_name:       'input[name="last_name"]',
      position:        'input[name="position"]',
      office:          'input[name="office"]',
      employment_type: 'select[name="employment_type"]',
      date_hired:      'input[name="date_hired"]',
      status:          'select[name="status"]',
    },
  };

  let mode = "add"; // 'add' | 'edit'

  // ── Utils ──
  function setSubmitting($btn, submitting) {
    if (!$btn.length) return;
    if (submitting) $btn.data("orig-text", $btn.html());
    $btn.prop("disabled", submitting)
        .html(submitting ? "Saving…" : $btn.data("orig-text") || (mode === "edit" ? "Save Changes" : "Add HR"));
  }

  function ensureFeedbackArea() {
    if (!document.querySelector(selectors.fb)) {
      $(selectors.form).find(".modal-body").prepend('<div id="addHrFeedback" class="d-none mb-2"></div>');
    }
  }

  function showFeedback(msg, type = "danger") {
    ensureFeedbackArea();
    const $box = $(selectors.fb);
    $box.removeClass("d-none alert-success alert-danger alert-warning alert-info")
        .addClass(`alert alert-${type}`)
        .html(msg);
  }

  function clearFeedback() {
    const $box = $(selectors.fb);
    $box.addClass("d-none")
        .removeClass("alert alert-success alert-danger alert-warning alert-info")
        .empty();
  }

  function refreshHRTable() {
    const $wrap = $(selectors.table);
    if (!$wrap.length) return;
    $wrap.addClass("position-relative");
    const loaderId = "hrTableLoader";
    if (!document.getElementById(loaderId)) {
      $wrap.append(
        `<div id="${loaderId}" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.6);z-index:1;">
           <div class="spinner-border" role="status" aria-label="Loading HR table"></div>
         </div>`
      );
    }
    $wrap.load(endpoints.hrTable, function () {
      $("#" + loaderId).remove();
      $wrap.removeClass("position-relative");
    });
  }

  function isEmail(v)    { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
  function isUsername(v) { return /^[A-Za-z0-9._-]{3,50}$/.test(v); }

  function getVal(name)  { return $.trim($(selectors.form).find(selectors.i[name]).val() || ""); }
  function setVal(name, v){ $(selectors.form).find(selectors.i[name]).val(v == null ? "" : v); }

  function ensureHiddenUserId() {
    if (!$(selectors.form).find(selectors.i.user_id).length) {
      $(selectors.form).prepend('<input type="hidden" name="user_id" />');
    }
  }

  function setModeAdd() {
    mode = "add";
    $(selectors.title).text("Add HR");
    const $form = $(selectors.form)[0];
    if ($form) $form.reset();
    clearFeedback();
    ensureHiddenUserId();
    setVal("user_id", "");
    $(selectors.form).find(selectors.i.password).attr("required", true).attr("placeholder", "");
    $(selectors.btn).html("Add HR");
  }

  function setModeEdit(data) {
    mode = "edit";
    $(selectors.title).text("Edit HR");
    clearFeedback();
    ensureHiddenUserId();

    // Fill in values
    setVal("user_id", data.id || "");
    setVal("username", data.username || "");
    setVal("email", data.email || "");
    setVal("employee_number", data.employee_number || "");
    setVal("first_name", data.first_name || "");
    setVal("middle_name", data.middle_name || "");
    setVal("last_name", data.last_name || "");
    setVal("position", data.position || "HR Officer");
    setVal("office", data.office || "HR Division");
    $(selectors.form).find(selectors.i.employment_type).val(data.employment_type || "Non-Teaching");
    setVal("date_hired", data.date_hired || "");
    $(selectors.form).find(selectors.i.status).val(data.status || "Active");

    $(selectors.form).find(selectors.i.password)
      .val("")
      .removeAttr("required")
      .attr("placeholder", "Leave blank to keep current password");

    $(selectors.btn).html("Save Changes");
    $(selectors.modal).modal("show");
  }

  // ── Build payload ──
  function collectPayload() {
    if (mode === "add") {
      return {
        username: getVal("username"),
        email:    getVal("email"),
        password: getVal("password"),
        employee_number: getVal("employee_number"),
        first_name:      getVal("first_name"),
        middle_name:     getVal("middle_name"),
        last_name:       getVal("last_name"),
        position:        getVal("position"),
        office:          getVal("office"),
        employment_type: $(selectors.form).find(selectors.i.employment_type).val(),
        date_hired:      getVal("date_hired"),
        status:          $(selectors.form).find(selectors.i.status).val()
      };
    } else {
      const p = {
        edit_hr_id:       getVal("user_id"),
        edit_hr_username: getVal("username"),
        edit_hr_email:    getVal("email"),
        edit_hr_status:   $(selectors.form).find(selectors.i.status).val(),
        employee_number:  getVal("employee_number"),
        first_name:       getVal("first_name"),
        middle_name:      getVal("middle_name"),
        last_name:        getVal("last_name"),
        position:         getVal("position"),
        office:           getVal("office"),
        employment_type:  $(selectors.form).find(selectors.i.employment_type).val(),
        date_hired:       getVal("date_hired"),
      };
      const newPass = getVal("password");
      if (newPass) p.edit_hr_password = newPass;
      return p;
    }
  }

  // ── Validation ──
  function validatePayload(p) {
    const missing = [];
    if (mode === "add") {
      if (!p.username || !isUsername(p.username)) missing.push("Valid Username (3–50 chars, letters/numbers . _ -)");
      if (!p.email || !isEmail(p.email))         missing.push("Valid Email");
      if (!p.password || p.password.length < 8)  missing.push("Password (min 8 chars)");
      if (!p.employee_number) missing.push("Employee Number");
      if (!p.first_name)      missing.push("First Name");
      if (!p.last_name)       missing.push("Last Name");
      if (!p.position)        missing.push("Position");
      if (!p.office)          missing.push("Office");
      if (!p.date_hired)      missing.push("Date Hired");
    } else {
      if (!p.edit_hr_id) missing.push("User ID");
      if (!p.edit_hr_username || !isUsername(p.edit_hr_username)) missing.push("Valid Username");
      if (!p.edit_hr_email || !isEmail(p.edit_hr_email))          missing.push("Valid Email");
    }
    return missing;
  }

  // ── Submit ──
  function bindFormSubmit() {
    $(document).on("submit", selectors.form, function (e) {
      e.preventDefault();
      clearFeedback();

      const $btn = $(selectors.btn);
      const payload = collectPayload();
      const missing = validatePayload(payload);
      if (missing.length) {
        showFeedback("Please provide: <br>• " + missing.join("<br>• "), "warning");
        return;
      }

      setSubmitting($btn, true);

      $.ajax({
        url: mode === "edit" ? endpoints.updateHR : endpoints.addHR,
        method: "POST",
        data: payload,
        dataType: "json",
        timeout: 20000,
      })
        .done(function (res, _status, xhr) {
          if (res && res.success) {
            showFeedback(res.message || (mode === "edit" ? "HR updated." : "HR added."), "success");
            refreshHRTable();
            setTimeout(function () {
              $(selectors.modal).modal("hide");
            }, 500);
          } else {
            const msg = (res && res.message) ? res.message : `Operation failed (HTTP ${xhr.status}).`;
            showFeedback(msg, "danger");
          }
        })
        .fail(function (xhr) {
          const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message)
            ? xhr.responseJSON.message
            : (xhr && xhr.responseText) ? xhr.responseText : "Server error.";
          showFeedback(msg, "danger");
        })
        .always(function () {
          setSubmitting($btn, false);
        });
    });
  }

  // ── Add & Edit bindings ──
  function bindAddOpen() {
    $(selectors.modal).on("show.bs.modal", function (e) {
      const trigger = e.relatedTarget || null;
      const launchedByEdit = !!(trigger && $(trigger).hasClass("edit-hr-btn"));
      if (!launchedByEdit && mode !== "edit") setModeAdd();
    });

    ensureHiddenUserId();

    $(selectors.modal).on("hidden.bs.modal", function () {
      setModeAdd();
      clearFeedback();
      setSubmitting($(selectors.btn), false);
    });
  }

  function bindEdit() {
    $(document).on("click", ".edit-hr-btn", function () {
      const $b = $(this);
      const data = {
        id:              $b.data("id"),
        username:        $b.data("username"),
        email:           $b.data("email"),
        employee_number: $b.data("employee_number") || "",
        first_name:      $b.data("first_name") || "",
        middle_name:     $b.data("middle_name") || "",
        last_name:       $b.data("last_name") || "",
        position:        $b.data("position") || "HR Officer",
        office:          $b.data("office") || "HR Division",
        employment_type: $b.data("employment_type") || "Non-Teaching",
        date_hired:      $b.data("date_hired") || "",
        status:          $b.data("status") || "Active",
      };
      setModeEdit(data);
    });
  }

  function bindDelete() {
    $(document).on("click", ".delete-hr-btn", function () {
      const id = parseInt($(this).data("id"), 10);
      if (!id) return;

      if (!confirm("Remove this HR? If the account has history it may be demoted/deactivated instead of deleted.")) {
        return;
      }

      const $btn = $(this);
      const orig = $btn.html();
      $btn.prop("disabled", true).html("Removing…");

     $.ajax({
      url: endpoints.deleteHR,
      method: "POST",
      data: { user_id: id },   // ✅ match delete_hr.php
      dataType: "json",
      timeout: 15000,
    })
        .done(function (res) {
          if (res && res.success) {
            alert(res.message || "Removed.");
            refreshHRTable();
          } else {
            alert((res && res.message) ? res.message : "Failed to remove HR.");
          }
        })
        .fail(function (xhr) {
          const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message)
            ? xhr.responseJSON.message
            : (xhr && xhr.responseText) ? xhr.responseText : "Server error.";
          alert(msg);
        })
        .always(function () {
          $btn.prop("disabled", false).html(orig);
        });
    });
  }

  // ── Init ──
  $(function () {
    ensureFeedbackArea();
    setModeAdd();
    bindFormSubmit();
    bindAddOpen();
    bindEdit();
    bindDelete();
  });
})(jQuery);
