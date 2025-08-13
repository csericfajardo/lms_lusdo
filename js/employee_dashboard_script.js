// employee_dashboard_script.js
(function () {
  const qs = (s, el = document) => el.querySelector(s);
  const qsa = (s, el = document) => Array.from(el.querySelectorAll(s));

  const endpoints = window.EDL_ENDPOINTS || {};

  // Elements
  const personalBtn = qs("#btnPersonal");
  const personalModal = qs("#personalModal");
  const pd = {
    employeeNo: qs("#pdEmployeeNo"),
    name: qs("#pdName"),
    email: qs("#pdEmail"),
    position: qs("#pdPosition"),
    empType: qs("#pdEmpType"),
  };

  const leaveGrid = qs("#leaveCreditsGrid");
  const ctoGrid = qs("#ctoCreditsGrid");

  const applyModal = qs("#applyModal");
  const applyForm = qs("#applyForm");
  const applyCancel = qs("#applyCancel");
  const applyDynamicFields = qs("#applyDynamicFields");
  const applyTitle = qs("#applyModalTitle");
  const applyLeaveTypeInput = qs("#applyLeaveType");

  const applicationsBody = qs("#applicationsBody");
  const viewModal = qs("#viewModal");
  const viewDetails = qs("#viewDetails");
  const btnCancelApplication = qs("#btnCancelApplication");

  let currentViewApplicationId = null;

  // Utilities
  function openDialog(dlg) {
    if (!dlg.open) dlg.showModal();
  }
  function closeDialog(dlg) {
    if (dlg.open) dlg.close();
  }
  function htmlToNode(html) {
    const t = document.createElement("template");
    t.innerHTML = html.trim();
    return t.content.firstChild;
  }
  async function getJSON(url, params = {}) {
    const usp = new URLSearchParams(params);
    const resp = await fetch(
      url + (usp.toString() ? "?" + usp.toString() : ""),
      { credentials: "include" }
    );
    if (!resp.ok) throw new Error("Network error");
    return resp.json();
  }
  async function postForm(url, data) {
    const formData = new FormData();
    Object.entries(data).forEach(([k, v]) => {
      formData.append(k, v);
    });
    const resp = await fetch(url, {
      method: "POST",
      body: formData,
      credentials: "include",
    });
    if (!resp.ok) throw new Error("Network error");
    return resp.json().catch(() => ({}));
  }

  // Load personal details (header + modal)
  async function loadPersonalDetails() {
    try {
      const info = await getJSON(endpoints.employeeDetails);
      // Fill modal fields
      pd.employeeNo.textContent = info.employee_no ?? "—";
      pd.name.textContent = info.name ?? "—";
      pd.email.textContent = info.email ?? "—";
      pd.position.textContent = info.position ?? "—";
      pd.empType.textContent = info.employment_type ?? "—";
      // Fill header inline fields
      const empNoEl = document.getElementById("empNo");
      const empNameEl = document.getElementById("empName");
      if (empNoEl) empNoEl.textContent = info.employee_no ?? "—";
      if (empNameEl) empNameEl.textContent = info.name ?? "—";
    } catch (e) {
      console.error("Failed to load personal details.", e);
    }
  }

  personalBtn?.addEventListener("click", () => {
    openDialog(personalModal);
  });

  // Field builder (HR style)
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
          <label class="edl-field">
            <span>${label}</span>
            <input type="date" name="${field}" required>
          </label>`;
      case "number_of_days":
        return `
          <label class="edl-field">
            <span>Number of Days</span>
            <input type="number" step="0.01" min="0" name="number_of_days" required>
          </label>`;
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
          <label class="edl-field">
            <span>${label}</span>
            <input type="file" name="${field}" required>
          </label>`;
      default:
        return `
          <label class="edl-field">
            <span>${label}</span>
            <input type="text" name="${field}" required>
          </label>`;
    }
  }

  // Open Apply Leave modal dynamically
  async function openApplyForType(t) {
    applyDynamicFields.innerHTML = "";
    applyTitle.textContent = `Apply for ${t.name || t.code}`;
    applyLeaveTypeInput.value = t.code;

    // CTO case
    if (t.code === "CTO") {
      applyDynamicFields.innerHTML = `
        ${buildField("date_from")}
        ${buildField("date_to")}
        ${buildField("number_of_days")}
        <label class="edl-field">
          <span>CTO Hours to Use</span>
          <input type="number" name="cto_hours" step="0.5" min="0.5">
        </label>
        <input type="hidden" name="notify_hr" value="1">
      `;
      openDialog(applyModal);
      return;
    }

    // Non-CTO: fetch required fields dynamically
    try {
      const res = await getJSON(
        `/depedlu_lms/users/empdash_get_leave_fields.php`,
        {
          leave_type_id: t.leave_type_id,
        }
      );
      if (!res.success) {
        alert(res.message || "Failed to load leave fields.");
        return;
      }
      (res.required_fields || []).forEach((f) => {
        applyDynamicFields.innerHTML += buildField(f);
      });
      applyDynamicFields.innerHTML += `<input type="hidden" name="notify_hr" value="1">`;
      openDialog(applyModal);
    } catch {
      alert("Error fetching leave fields.");
    }
  }

  applyCancel?.addEventListener("click", () => closeDialog(applyModal));

  applyForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(applyForm).entries());
    try {
      await postForm(endpoints.applyLeave, data);
      closeDialog(applyModal);
      await Promise.all([
        loadApplications(),
        loadLeaveCredits(),
        loadCtoCredits(),
      ]);
      try {
        await postForm(endpoints.notify, { type: "leave_apply" });
      } catch {}
      alert("Leave application submitted.");
    } catch {
      alert("Failed to submit leave application.");
    }
  });

  // Leave credits
  async function loadLeaveCredits() {
    try {
      const types = await getJSON(endpoints.availableLeaveTypes);
      leaveGrid.innerHTML = "";
      (types || []).forEach((t) => {
        const box = htmlToNode(`
          <button class="edl-credit-box" 
            data-code="${t.code}" 
            data-id="${t.leave_type_id}">
            <span class="title">${t.name}</span>
            <span class="balance">${t.balance ?? 0}</span>
            <span class="update">Updated: ${t.last_update ?? "—"}</span>
          </button>
        `);
        box.addEventListener("click", () => openApplyForType(t));
        leaveGrid.appendChild(box);
      });
    } catch {}
  }

  async function loadCtoCredits() {
    try {
      const res = await getJSON(endpoints.ctoCredits);
      ctoGrid.innerHTML = "";
      const box = htmlToNode(`
        <button class="edl-credit-box" data-code="CTO">
          <span class="title">CTO</span>
          <span class="balance">${res.available_hours ?? 0}</span>
          <span class="update">Updated: ${res.last_update ?? "—"}</span>
        </button>
      `);
      box.addEventListener("click", () =>
        openApplyForType({ code: "CTO", name: "Compensatory Time-Off" })
      );
      ctoGrid.appendChild(box);
    } catch {}
  }

  // Applications
  async function loadApplications() {
    try {
      const resp = await fetch(endpoints.applicationsTable, {
        credentials: "include",
      });
      if (resp.ok) {
        const html = await resp.text();
        const tmp = document.createElement("div");
        tmp.innerHTML = html;
        const rows = tmp.querySelectorAll("tbody tr, tr");
        applicationsBody.innerHTML = "";
        rows.forEach((r) => {
          const ref = r.firstElementChild?.textContent?.trim() || "";
          const lastCell = r.lastElementChild;
          if (!lastCell || !/View/i.test(lastCell.textContent)) {
            const td = document.createElement("td");
            td.innerHTML = `<button class="btn btn-link view-app" data-ref="${ref}">View</button>`;
            r.appendChild(td);
          } else {
            lastCell.innerHTML = `<button class="btn btn-link view-app" data-ref="${ref}">View</button>`;
          }
          applicationsBody.appendChild(r);
        });
        qsa(".view-app", applicationsBody).forEach((btn) => {
          btn.addEventListener("click", () =>
            openApplicationDetails(btn.dataset.ref)
          );
        });
      }
    } catch {}
  }

  function openApplicationDetails(ref) {
    currentViewApplicationId = ref;
    const row = qsa("tr", applicationsBody).find(
      (tr) => tr.firstElementChild?.textContent?.trim() === ref
    );
    let kv = [];
    if (row) {
      kv = [
        ["Reference #", row.children[0]?.textContent?.trim() || "—"],
        ["Type", row.children[1]?.textContent?.trim() || "—"],
        ["Status", row.children[2]?.textContent?.trim() || "—"],
        ["Approver", row.children[3]?.textContent?.trim() || "—"],
        ["Dates", row.children[4]?.textContent?.trim() || "—"],
      ];
    }
    viewDetails.innerHTML = kv
      .map(
        ([k, v]) =>
          `<div><span class="k">${k}</span><span class="v">${v}</span></div>`
      )
      .join("");
    openDialog(viewModal);
  }

  btnCancelApplication?.addEventListener("click", async () => {
    if (!currentViewApplicationId) return;
    const sure = confirm(
      "Cancel this application? This cannot be undone. You may apply again afterwards."
    );
    if (!sure) return;
    try {
      await postForm(endpoints.cancelLeave, {
        action: "cancel",
        application_id: currentViewApplicationId,
        notify_hr: 1,
      });
      try {
        await postForm(endpoints.notify, { type: "leave_cancel" });
      } catch {}
      closeDialog(viewModal);
      await loadApplications();
      alert("Application canceled.");
    } catch {
      alert("Failed to cancel application.");
    }
  });

  // Init
  loadPersonalDetails();
  loadLeaveCredits();
  loadCtoCredits();
  loadApplications();
})();
