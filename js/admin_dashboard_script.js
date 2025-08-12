// admin_script.js
// Requires jQuery + Bootstrap (already on your page)

(function ($) {
  const endpoints = {
    addHR: "/depedlu_lms/users/add_hr.php",
    hrTable: "/depedlu_lms/users/get_hr_table.php",
  };

  const selectors = {
    addForm: "#addHRForm",
    addModal: "#addHRModal",
    addBtn: '#addHRForm button[type="submit"]',
    hrTableWrap: "#hrTable",
    feedback: "#addHrFeedback",
  };

  // --- Utils --------------------------------------------------------------

  function setSubmitting($btn, submitting) {
    if (!$btn.length) return;
    if (submitting) {
      $btn.data("orig-text", $btn.html());
    }
    $btn
      .prop("disabled", submitting)
      .html(submitting ? "Saving…" : $btn.data("orig-text") || "Add HR");
  }

  function showFeedback(msg, type = "danger") {
    // type: 'success' | 'danger' | 'warning' | 'info'
    const $box = $(selectors.feedback);
    if ($box.length) {
      $box
        .removeClass(
          "d-none alert-success alert-danger alert-warning alert-info"
        )
        .addClass(`alert alert-${type}`)
        .html(msg);
    } else {
      // fallback
      alert(msg);
    }
  }

  function clearFeedback() {
    const $box = $(selectors.feedback);
    if ($box.length)
      $box
        .addClass("d-none")
        .removeClass(
          "alert alert-success alert-danger alert-warning alert-info"
        )
        .empty();
  }

  function refreshHRTable() {
    const $wrap = $(selectors.hrTableWrap);
    if (!$wrap.length) return;
    $wrap.addClass("position-relative");
    const loaderId = "hrTableLoader";
    if (!document.getElementById(loaderId)) {
      $wrap.append(
        `<div id="${loaderId}" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.6);z-index:1;">
           <div class="spinner-border" role="status" aria-label="Loading HR table"></div>
         </div>`
      );
    }
    $wrap.load(endpoints.hrTable, function () {
      $("#" + loaderId).remove();
      $wrap.removeClass("position-relative");
    });
  }

  // --- Add HR: AJAX submit -----------------------------------------------

  function bindAddHR() {
    $(document).on("submit", selectors.addForm, function (e) {
      e.preventDefault();
      clearFeedback();

      const $form = $(this);
      const $btn = $(selectors.addBtn);

      // Collect fields explicitly to avoid stray inputs
      const payload = {
        username: $.trim($form.find('input[name="username"]').val() || ""),
        email: $.trim($form.find('input[name="email"]').val() || ""),
        password: $.trim($form.find('input[name="password"]').val() || ""),
      };

      // Quick client-side checks to give immediate feedback
      if (!payload.username || !payload.email || !payload.password) {
        showFeedback("Please fill in all fields.", "warning");
        return;
      }

      setSubmitting($btn, true);

      $.ajax({
        url: endpoints.addHR,
        method: "POST",
        data: payload,
        dataType: "json",
        timeout: 15000,
      })
        .done(function (res) {
          if (res && res.success) {
            showFeedback(res.message || "HR added successfully.", "success");
            // Refresh table immediately
            refreshHRTable();
            // Close modal after a short delay to show success
            setTimeout(function () {
              $(selectors.addModal).modal("hide");
            }, 400);
          } else {
            showFeedback(
              res && res.message ? res.message : "Failed to add HR.",
              "danger"
            );
          }
        })
        .fail(function (xhr) {
          const msg =
            xhr && xhr.responseJSON && xhr.responseJSON.message
              ? xhr.responseJSON.message
              : "Server error. Please try again.";
          showFeedback(msg, "danger");
        })
        .always(function () {
          setSubmitting($btn, false);
        });
    });
  }

  // --- Modal lifecycle: reset form on close ------------------------------

  function bindModalReset() {
    $(selectors.addModal).on("hidden.bs.modal", function () {
      const $form = $(selectors.addForm);
      if ($form.length) {
        $form[0].reset();
      }
      clearFeedback();
      setSubmitting($(selectors.addBtn), false);
    });
  }

  // --- Init --------------------------------------------------------------

  $(function () {
    // Inject a feedback area into the Add HR modal footer if not present
    if (!document.querySelector(selectors.feedback)) {
      $(selectors.addForm)
        .find(".modal-body")
        .prepend('<div id="addHrFeedback" class="d-none mb-2"></div>');
    }

    bindAddHR();
    bindModalReset();

    // Optional: if the Manage HR tab is visible on load, ensure table is fresh
    // (Uncomment if you want an initial refresh)
    // refreshHRTable();
  });
})(jQuery);
