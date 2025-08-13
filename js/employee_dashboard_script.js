// employee_dashboard_script.js
// Mobile-first behavior for the employee dashboard
(function () {
  const qs = (s, el = document) => el.querySelector(s);
  const qsa = (s, el = document) => Array.from(el.querySelectorAll(s));

  const endpoints = window.EDL_ENDPOINTS || {};

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

  // 1) Personal details
  personalBtn?.addEventListener("click", async () => {
    try {
      const info = await getJSON(endpoints.employeeDetails);
      pd.employeeNo.textContent = info.employee_no ?? "—";
      pd.name.textContent = info.name ?? "—";
      pd.email.textContent = info.email ?? "—";
      pd.position.textContent = info.position ?? "—";
      pd.empType.textContent = info.employment_type ?? "—";
      openDialog(personalModal);
    } catch (e) {
      alert("Failed to load personal details.");
    }
  });

  // 2) Leave credits + dynamic apply
  async function loadLeaveCredits() {
    try {
      const types = await getJSON(endpoints.availableLeaveTypes); // [{code, name, balance}]
      leaveGrid.innerHTML = "";
      (types || []).forEach((t) => {
        const box = htmlToNode(`
          <button class="edl-credit-box" data-code="${t.code}">
            <span class="title">${t.name}</span>
            <span class="balance">${t.balance ?? 0}</span>
          </button>
        `);
        box.addEventListener("click", () => openApplyForType(t));
        leaveGrid.appendChild(box);
      });
    } catch (e) {
      // silent; may be populated later by server-side
    }
  }

  async function loadCtoCredits() {
    try {
      const res = await getJSON(endpoints.ctoCredits); // {available_hours: number}
      ctoGrid.innerHTML = "";
      const box = htmlToNode(`
        <button class="edl-credit-box" data-code="CTO">
          <span class="title">CTO</span>
          <span class="balance">${res.available_hours ?? 0}</span>
        </button>
      `);
      box.addEventListener("click", () =>
        openApplyForType({ code: "CTO", name: "Compensatory Time-Off" })
      );
      ctoGrid.appendChild(box);
    } catch (e) {
      // ignore
    }
  }

  function openApplyForType(t) {
    applyTitle.textContent = `Apply for ${t.name || t.code}`;
    applyLeaveTypeInput.value = t.code;
    // Build minimal dynamic fields (date range + reason). Server validates specifics per type.
    applyDynamicFields.innerHTML = `
      <label class="edl-field">
        <span>Start Date</span>
        <input type="date" name="start_date" required />
      </label>
      <label class="edl-field">
        <span>End Date</span>
        <input type="date" name="end_date" required />
      </label>
      <label class="edl-field">
        <span>Reason</span>
        <textarea name="reason" rows="3" placeholder="Reason for leave" required></textarea>
      </label>
      ${
        t.code === "CTO"
          ? `
        <label class="edl-field">
          <span>CTO Hours to Use</span>
          <input type="number" name="cto_hours" step="0.5" min="0.5" />
        </label>
      `
          : ""
      }
      <input type="hidden" name="notify_hr" value="1" />
    `;
    openDialog(applyModal);
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
    } catch (e) {
      alert("Failed to submit leave application.");
    }
  });

  // 3) Applications table
  async function loadApplications() {
    // Expect HTML rows or JSON; try HTML tbody rows first.
    try {
      const resp = await fetch(endpoints.applicationsTable, {
        credentials: "include",
      });
      if (resp.ok) {
        const html = await resp.text();
        // If endpoint returns a full table, extract rows; else assume it's rows only.
        const tmp = document.createElement("div");
        tmp.innerHTML = html;
        const rows = tmp.querySelectorAll("tbody tr, tr");
        applicationsBody.innerHTML = "";
        rows.forEach((r) => {
          // Add a "View" action if missing
          const lastCell = r.lastElementChild;
          const ref = r.firstElementChild?.textContent?.trim() || "";
          if (!lastCell || !/View/i.test(lastCell.textContent)) {
            const td = document.createElement("td");
            td.innerHTML = `<button class="btn btn-link view-app" data-ref="${ref}">View</button>`;
            r.appendChild(td);
          } else {
            // rewrite to a button for consistency, keep any data-ref if present
            lastCell.innerHTML = `<button class="btn btn-link view-app" data-ref="${ref}">View</button>`;
          }
          applicationsBody.appendChild(r);
        });
        // bind view handlers
        qsa(".view-app", applicationsBody).forEach((btn) => {
          btn.addEventListener("click", () =>
            openApplicationDetails(btn.dataset.ref)
          );
        });
      }
    } catch (e) {
      // ignore
    }
  }

  async function openApplicationDetails(ref) {
    currentViewApplicationId = ref;
    // If you have a details endpoint, call it; else, compose from table row content.
    const row = qsa("tr", applicationsBody).find(
      (tr) => tr.firstElementChild?.textContent?.trim() === ref
    );
    let kv = [];
    if (row) {
      kv = [
        ["Reference #", row.children[0]?.textContent?.trim() || "—"],
        ["Type", row.children[1]?.textContent?.trim() || "—"],
        ["Date/s", row.children[2]?.textContent?.trim() || "—"],
        ["Status", row.children[3]?.textContent?.trim() || "—"],
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
    } catch (e) {
      alert("Failed to cancel application.");
    }
  });

  // Init
  loadLeaveCredits();
  loadCtoCredits();
  loadApplications();
})();
