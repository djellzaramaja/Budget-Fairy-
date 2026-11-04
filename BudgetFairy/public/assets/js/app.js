document.addEventListener('DOMContentLoaded', () => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const money = v => new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR' }).format(v);
  const PALETTE = ['#7254a8', '#e8789a', '#3fb8a5', '#f2b84b', '#6aa9e9', '#b28be0', '#f59e7c', '#8fcf9a'];
  const el = id => document.getElementById(id);

  /* ---------- Networking ---------- */
  async function postJSON(url, data) {
    const response = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(data)
    });
    let result = {};
    try { result = await response.json(); } catch (_) { /* non-JSON error page */ }
    if (response.status === 401) { location.href = 'login.php'; throw new Error('Please log in again.'); }
    if (!response.ok) throw new Error(result.error || 'Something went wrong.');
    return result;
  }

  /* ---------- Toasts (survive a page reload via sessionStorage) ---------- */
  function toast(message, kind = 'success') {
    const wrap = el('toastWrap'); if (!wrap) return;
    const t = document.createElement('div');
    t.className = `toast bf-toast bf-toast-${kind} align-items-center border-0`;
    t.setAttribute('role', 'status');
    const inner = document.createElement('div'); inner.className = 'd-flex';
    const body = document.createElement('div'); body.className = 'toast-body'; body.textContent = message;
    const close = document.createElement('button'); close.type = 'button'; close.className = 'btn-close me-2 m-auto'; close.setAttribute('data-bs-dismiss', 'toast'); close.setAttribute('aria-label', 'Close');
    inner.append(body, close); t.append(inner); wrap.append(t);
    bootstrap.Toast.getOrCreateInstance(t, { delay: 3200 }).show();
    t.addEventListener('hidden.bs.toast', () => t.remove());
  }
  const reloadWithToast = message => { try { sessionStorage.setItem('bf_toast', message); } catch (_) {} location.reload(); };
  try { const m = sessionStorage.getItem('bf_toast'); if (m) { sessionStorage.removeItem('bf_toast'); toast(m); } } catch (_) {}

  /* ---------- Friendly confirm dialog (replaces the browser's confirm box) ---------- */
  function confirmDialog(text) {
    return new Promise(resolve => {
      const modalEl = el('confirmModal');
      if (!modalEl) return resolve(window.confirm(text));
      el('confirmText').textContent = text;
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      const yes = el('confirmYes'), no = el('confirmNo');
      let answered = false;
      const done = v => { if (answered) return; answered = true; yes.onclick = no.onclick = null; modalEl.removeEventListener('hidden.bs.modal', onHide); modal.hide(); resolve(v); };
      const onHide = () => done(false);
      yes.onclick = () => done(true); no.onclick = () => done(false);
      modalEl.addEventListener('hidden.bs.modal', onHide);
      modal.show();
    });
  }

  const setMsg = (box, text, cls) => { if (box) { box.textContent = text; box.className = `form-message small mt-2 ${cls || ''}`; } };

  /* ---------- Generic forms: create / edit / contribute / recurring ---------- */
  document.querySelectorAll('form[data-api]').forEach(form => {
    const msg = form.querySelector('.form-message');
    const title = form.closest('.modal, .panel-card')?.querySelector('[data-form-title]');
    const submitBtn = form.querySelector('[data-submit-label]');
    const cancelBtn = form.querySelector('[data-cancel]');
    const defaults = Object.fromEntries(new FormData(form).entries());

    const setMode = editing => {
      if (title) title.textContent = editing ? title.dataset.editTitle : title.dataset.addTitle;
      if (submitBtn) submitBtn.textContent = editing ? submitBtn.dataset.editLabel : submitBtn.dataset.addLabel;
      cancelBtn?.classList.toggle('d-none', !editing);
    };
    const reset = () => {
      form.reset();
      Object.entries(defaults).forEach(([k, v]) => { if (form.elements[k]) form.elements[k].value = v; });
      if (form.elements['id']) form.elements['id'].value = '';
      setMsg(msg, ''); setMode(false);
    };
    cancelBtn?.addEventListener('click', reset);

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      const editing = !!payload.id;
      payload.action = form.dataset.action || (editing ? 'update' : 'create');
      if (!payload.id) delete payload.id;
      try {
        setMsg(msg, 'Saving...');
        const result = await postJSON(form.dataset.api, payload);
        reloadWithToast(result.message || 'Saved!');
      } catch (error) { setMsg(msg, error.message, 'text-danger'); }
    });

    if (form.id === 'transactionForm') {
      document.querySelectorAll('[data-tx-add]').forEach(btn => btn.addEventListener('click', () => {
        reset(); bootstrap.Modal.getOrCreateInstance(el('transactionModal')).show();
      }));
    }
    document.querySelectorAll('[data-edit]').forEach(btn => {
      const target = document.querySelector(btn.dataset.target);
      if (!target || !target.contains(form)) return;
      btn.addEventListener('click', () => {
        reset();
        Object.entries(JSON.parse(btn.dataset.edit)).forEach(([k, v]) => { if (form.elements[k]) form.elements[k].value = v ?? ''; });
        setMode(true);
        if (target.classList.contains('modal')) bootstrap.Modal.getOrCreateInstance(target).show();
        else target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    });
  });

  /* ---------- Add money to a goal ---------- */
  const contributeForm = el('contributeForm');
  if (contributeForm) {
    const modalEl = el('contributeModal');
    document.querySelectorAll('[data-contribute]').forEach(btn => btn.addEventListener('click', () => {
      const g = JSON.parse(btn.dataset.contribute);
      contributeForm.reset(); contributeForm.elements['id'].value = g.id;
      el('contributeTitle').textContent = `Goal: ${g.title}`;
      setMsg(contributeForm.querySelector('.form-message'), '');
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      setTimeout(() => contributeForm.elements['amount'].focus(), 300);
    }));
    contributeForm.querySelectorAll('[data-quick]').forEach(b => b.addEventListener('click', () => { contributeForm.elements['amount'].value = b.dataset.quick; }));
  }

  /* ---------- Delete buttons ---------- */
  document.querySelectorAll('[data-delete-url]').forEach(btn => btn.addEventListener('click', async () => {
    if (!(await confirmDialog(btn.dataset.confirm || 'Delete this item?'))) return;
    btn.disabled = true;
    try { const r = await postJSON(btn.dataset.deleteUrl, { action: 'delete', id: Number(btn.dataset.id) }); reloadWithToast(r.message || 'Deleted.'); }
    catch (error) { toast(error.message, 'error'); btn.disabled = false; }
  }));

  /* Selects that filter on change (CSP forbids inline onchange) */
  document.querySelectorAll('[data-autosubmit]').forEach(s => s.addEventListener('change', () => s.form.submit()));

  /* ---------- Dashboard charts ---------- */
  const readChart = id => { const c = el(id); if (!c) return null; try { return [c, JSON.parse(c.dataset.chart)]; } catch (_) { return null; } };
  const cat = readChart('categoryChart');
  if (cat) {
    new Chart(cat[0], {
      type: 'doughnut',
      data: { labels: cat[1].labels, datasets: [{ data: cat[1].values, backgroundColor: PALETTE, borderColor: '#fff', borderWidth: 3 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: { legend: { position: 'right', labels: { boxWidth: 12, usePointStyle: true } },
                   tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${money(ctx.parsed)}` } } } }
    });
  }
  const hist = readChart('historyChart');
  if (hist) {
    new Chart(hist[0], {
      type: 'bar',
      data: { labels: hist[1].labels, datasets: [
        { label: 'Income', data: hist[1].income, backgroundColor: '#3fb8a5', borderRadius: 6 },
        { label: 'Expenses', data: hist[1].expenses, backgroundColor: '#e8789a', borderRadius: 6 } ] },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${money(ctx.parsed.y)}` } } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '€' + v } }, x: { grid: { display: false } } } }
    });
  }

  /* ---------- "Can I afford this?" ---------- */
  const affordForm = el('affordForm');
  if (affordForm) {
    const box = el('affordResult');
    affordForm.addEventListener('submit', async event => {
      event.preventDefault();
      const price = Number(el('affordPrice').value);
      box.className = 'afford-result'; box.textContent = 'Fia is checking… ✨';
      try {
        const r = await postJSON('../api/afford.php', { price });
        const plural = n => `${n} month${n === 1 ? '' : 's'}`;
        let text, tone = 'warn';
        if (r.status === 'now') { text = `Yes! 🎉 You’ve saved ${money(r.saved)}, which covers ${money(r.price)} right now and leaves ${money(r.leftover)}.`; tone = 'good'; }
        else if (r.months_used === 0) text = 'Add some income and expenses first, so Fia can estimate how much you save each month.';
        else if (r.status === 'wait') { text = `Not right away. You’ve saved ${money(r.saved)} and usually save about ${money(r.monthly_savings)} a month, so you could afford ${money(r.price)} in about ${plural(r.months)}.`; tone = 'good'; }
        else if (r.status === 'too_long') text = `At your current pace this would take more than 10 years. Let’s find a faster plan.`;
        else text = `Right now your spending is at or above your income, so your savings aren’t growing (you’re ${money(r.shortfall)} short). Let’s find a plan.`;
        box.className = `afford-result afford-${tone}`;
        box.textContent = text + ' ';
        const link = document.createElement('a');
        link.href = `assistant.php?target=${encodeURIComponent(r.price)}`; link.className = 'text-link'; link.textContent = 'Plan it with Fia →';
        box.append(link);
      } catch (error) { box.className = 'afford-result afford-warn'; box.textContent = error.message; }
    });
  }

  /* ---------- Scenario comparison (numbers come from the server engine) ---------- */
  const simIds = ['simIncome', 'simExpenses', 'simSaved', 'simTarget', 'simMonths', 'simCut', 'simExtra', 'simMoreMonths'];
  let chart, timer;
  const scenarioPayload = () => ({
    income: Number(el('simIncome').value) || 0, expenses: Number(el('simExpenses').value) || 0,
    saved: Number(el('simSaved').value) || 0, target: Number(el('simTarget').value) || 0,
    months: Number(el('simMonths').value) || 1, expense_cut: Number(el('simCut').value) || 0,
    extra_income: Number(el('simExtra').value) || 0, extra_months: Number(el('simMoreMonths').value) || 0
  });
  function goalLine(r) {
    if (r.status === 'no_goal') return '';
    if (r.months_to_goal === 0) return '✓ Goal already covered';
    if (r.months_to_goal === null) return 'Goal not reachable at this rate';
    return `Goal in ~${r.months_to_goal} month${r.months_to_goal === 1 ? '' : 's'}`;
  }
  function render(c) {
    const o = c.original, a = c.adjusted, d = c.delta;
    el('monthsLabel').textContent = o.months; el('cutLabel').textContent = el('simCut').value;
    el('extraLabel').textContent = el('simExtra').value; el('moreMonthsLabel').textContent = el('simMoreMonths').value;
    el('origMonthly').textContent = money(o.monthly_savings); el('origProjected').textContent = money(o.projected_savings);
    el('adjMonthly').textContent = money(a.monthly_savings); el('adjProjected').textContent = money(a.projected_savings);
    el('origGoal').textContent = goalLine(o); el('adjGoal').textContent = goalLine(a);

    const status = el('goalStatus'); let text, warn = false;
    if (a.status === 'deficit') { text = `In the adjusted plan your expenses still exceed income by ${money(Math.abs(a.monthly_savings))} a month.`; warn = true; }
    else if (a.status === 'no_goal') text = `You would save ${money(a.monthly_savings)} a month. Enter a goal amount to compare against.`;
    else if (a.status === 'on_track') text = `On track! The adjusted plan ends ${money(a.surplus)} above your goal` + (d.months_to_goal > 0 ? `, reaching it ${d.months_to_goal} month${d.months_to_goal === 1 ? '' : 's'} sooner.` : '.');
    else { text = `Still ${money(a.shortfall)} short after ${a.months} months. Saving about ${money(a.required_monthly)} a month would close the gap.`; warn = true; }
    if (d.projected_savings !== 0) text += ` (Adjusted vs original: ${d.projected_savings > 0 ? '+' : ''}${money(d.projected_savings)}.)`;
    status.textContent = text; status.className = 'goal-status' + (warn ? ' warning' : '');

    const n = Math.max(o.series.length, a.series.length);
    const labels = Array.from({ length: n }, (_, i) => `Month ${i}`);
    const pad = s => Array.from({ length: n }, (_, i) => (i < s.length ? s[i] : null));
    if (chart) chart.destroy();
    chart = new Chart(el('projectionChart'), {
      type: 'line',
      data: { labels, datasets: [
        { label: 'Original plan', data: pad(o.series), borderColor: '#9a94a8', borderDash: [6, 4], backgroundColor: 'transparent', tension: .25, pointRadius: 2 },
        { label: 'Adjusted plan', data: pad(a.series), borderColor: '#3fb8a5', backgroundColor: 'rgba(63,184,165,.14)', fill: true, tension: .25, pointRadius: 3 } ] },
      options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { position: 'bottom' } },
        scales: { y: { ticks: { callback: v => '€' + v } }, x: { grid: { display: false } } } }
    });
  }
  async function recalc() {
    try { render(await postJSON('../api/simulate.php', scenarioPayload())); }
    catch (error) { el('goalStatus').textContent = error.message; el('goalStatus').className = 'goal-status warning'; }
  }
  if (simIds.every(id => el(id))) {
    const schedule = () => { clearTimeout(timer); timer = setTimeout(recalc, 250); };
    simIds.forEach(id => el(id).addEventListener('input', schedule));
    el('simForm')?.addEventListener('submit', e => e.preventDefault());
    el('simGoal')?.addEventListener('change', e => {
      const opt = e.target.selectedOptions[0];
      if (opt && opt.value) { el('simTarget').value = opt.dataset.target; el('simSaved').value = opt.dataset.saved; }
      schedule();
    });
    recalc();
  }

  /* ---------- Fia chat ---------- */
  const chatForm = el('chatForm');
  if (chatForm) {
    const messages = el('chatMessages'), input = el('chatInput');
    const addBubble = (text, kind) => {
      const b = document.createElement('div');
      b.className = `chat-bubble ${kind === 'user' ? 'user-bubble' : 'bot-bubble'}`;
      b.textContent = text;   // textContent: AI/user text is never parsed as HTML
      messages.appendChild(b); messages.scrollTop = messages.scrollHeight; return b;
    };
    document.querySelectorAll('.suggestion-chip').forEach(b => b.addEventListener('click', () => { input.value = b.textContent; input.focus(); }));
    chatForm.addEventListener('submit', async event => {
      event.preventDefault();
      const message = input.value.trim(); if (!message) return;
      addBubble(message, 'user'); input.value = '';
      const pending = addBubble('Let me look at your numbers… ✨', 'bot');
      try { pending.textContent = (await postJSON('../api/assistant.php', { message, ...scenarioPayload() })).reply; }
      catch (error) { pending.textContent = error.message || 'I could not answer just now. Please try again.'; }
    });
  }
});
