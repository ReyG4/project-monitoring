const projectId = new URLSearchParams(window.location.search).get('id');
const EDITABLE = ['perencanaan', 'berjalan', 'revisi'];

const PROJECT_STATUS = {
  perencanaan: ['Perencanaan', 'bg-warning-subtle text-warning-emphasis'],
  berjalan: ['Berjalan', 'bg-primary-subtle text-primary-emphasis'],
  diajukan: ['Diajukan', 'bg-info-subtle text-info-emphasis'],
  revisi: ['Revisi', 'bg-danger-subtle text-danger-emphasis'],
  selesai: ['Selesai', 'bg-success-subtle text-success-emphasis'],
};

const TASK_STATUS = {
  not_started: ['Not Started', 'bg-secondary-subtle text-secondary-emphasis'],
  in_progress: ['In Progress', 'bg-primary-subtle text-primary-emphasis'],
  blocked: ['Blocked', 'bg-danger-subtle text-danger-emphasis'],
  completed: ['Completed', 'bg-success-subtle text-success-emphasis'],
};

let project = null;
let tasks = [];
let meta = {};
let budget = {};
let budgetById = {};
let reviews = [];
let canEdit = false;
const expanded = new Set();
let editingId = null;
let parentForCreate = null;
let expenseTask = null;
let weightGroup = { parentId: null };

function showAlert(message) {
  const box = document.getElementById('alert');
  box.textContent = message;
  box.classList.remove('d-none');
}

function clearAlert() {
  document.getElementById('alert').classList.add('d-none');
}

function formatError(error) {
  return Object.values(error.errors || {}).flat().join(' ') || error.message;
}

function badge(text, cssClass) {
  return el('span', { class: 'badge rounded-pill ' + cssClass }, text);
}

async function load() {
  try {
    const [projectResult, taskResult, budgetResult, reviewResult] = await Promise.all([
      api('/projects/' + projectId),
      api('/projects/' + projectId + '/tasks'),
      api('/projects/' + projectId + '/budget'),
      api('/projects/' + projectId + '/reviews'),
    ]);

    project = projectResult.data;
    tasks = taskResult.data;
    meta = taskResult.meta || {};
    budget = budgetResult.data || {};
    reviews = reviewResult.data || [];

    budgetById = {};
    (budget.activities || []).forEach((activity) => {
      budgetById[activity.id] = activity;
      (activity.subtasks || []).forEach((sub) => { budgetById[sub.id] = sub; });
    });

    const user = currentUser();
    canEdit = user.role === 'project_manager'
      && project.created_by === user.id
      && EDITABLE.includes(project.status);

    renderHeader();
    renderBudget();
    renderRows();
    renderWorkflow();
  } catch (error) {
    showAlert(error.message);
  }
}

function renderHeader() {
  const [label, cssClass] = PROJECT_STATUS[project.status] || [project.status, 'bg-secondary-subtle'];

  document.getElementById('p-name').textContent = project.name;
  document.getElementById('p-desc').textContent = project.description || '';
  document.getElementById('p-progress').textContent = Math.round(meta.project_progress ?? 0) + '%';
  document.getElementById('p-dates').textContent =
    formatDate(project.start_date) + ' - ' + formatDate(project.target_end_date);

  const statusBadge = document.getElementById('p-status');
  statusBadge.className = 'badge rounded-pill ' + cssClass;
  statusBadge.textContent = label;

  document.getElementById('btn-add-root').classList.toggle('d-none', !canEdit);
  document.getElementById('btn-weights-root').classList.toggle('d-none', !canEdit || tasks.length === 0);

  const note = document.getElementById('readonly-note');
  const isManager = currentUser().role === 'project_manager';
  note.textContent = isManager && !canEdit
    ? 'Proyek berstatus ' + label + ' sehingga tidak bisa diubah.'
    : '';
  note.classList.toggle('d-none', note.textContent === '');
}

function renderBudget() {
  const summary = budget.project || { plan: 0, actual: 0, remaining: 0, usage_percent: null };

  document.getElementById('b-plan').textContent = formatRupiah(summary.plan);
  document.getElementById('b-actual').textContent = formatRupiah(summary.actual);

  const remaining = document.getElementById('b-remaining');
  remaining.textContent = formatRupiah(summary.remaining);
  remaining.className = 'h5 mb-0 ' + (summary.remaining < 0 ? 'text-danger' : 'text-success');

  document.getElementById('b-percent').textContent =
    summary.usage_percent === null ? 'N/A' : summary.usage_percent + '%';
}

function renderRows() {
  const rows = [];

  tasks.forEach((root) => {
    rows.push(taskRow(root, 0));

    if (expanded.has(root.id)) {
      (root.children || []).forEach((sub) => rows.push(taskRow(sub, 1)));
    }
  });

  document.getElementById('rows').replaceChildren(...rows);
  document.getElementById('empty').classList.toggle('d-none', tasks.length > 0);

  const total = Number(meta.weight_total ?? 0);
  const note = document.getElementById('weight-note');
  note.textContent = tasks.length ? 'Total bobot aktivitas utama: ' + total + '%' : '';
  note.className = 'small mb-2 ' + (Math.abs(total - 100) < 0.01 ? 'text-muted' : 'text-danger');
}

function toggle(id) {
  if (expanded.has(id)) {
    expanded.delete(id);
  } else {
    expanded.add(id);
  }
  renderRows();
}

function budgetCells(task) {
  const b = budgetById[task.id] || { plan: 0, actual: 0, usage_percent: null, over_budget: false };

  return [
    el('td', { class: 'text-end' }, formatRupiah(b.plan)),
    el('td', { class: 'text-end ' + (b.over_budget ? 'text-danger' : '') },
      formatRupiah(b.actual),
      el('div', { class: 'small text-muted' }, b.usage_percent === null ? 'N/A' : b.usage_percent + '%')
    ),
  ];
}

function progressCell(task) {
  if (task.is_leaf && canEdit) {
    const input = el('input', {
      type: 'number', min: '0', max: '100', step: '1',
      class: 'form-control form-control-sm', style: 'width: 90px;',
      value: String(Math.round(task.progress)), 'aria-label': 'Progres ' + task.name,
    });
    input.addEventListener('change', () => saveProgress(task, input.value));
    return el('div', { class: 'd-flex align-items-center gap-1' }, input, '%');
  }

  const percent = Math.round(task.progress);

  return el('div', { class: 'd-flex align-items-center gap-2' },
    el('div', { class: 'progress flex-grow-1' },
      el('div', { class: 'progress-bar', role: 'progressbar', style: 'width:' + percent + '%' })),
    el('small', {}, percent + '%')
  );
}

function taskRow(task, level) {
  const children = task.children || [];
  const [statusLabel, statusClass] = TASK_STATUS[task.status] || [task.status, 'bg-secondary-subtle'];
  const isRoot = level === 0;

  const nameCell = el('td', { class: isRoot ? '' : 'ps-5' },
    isRoot && children.length
      ? el('button', {
          class: 'btn btn-sm btn-link p-0 me-2 text-decoration-none',
          onclick: () => toggle(task.id),
        }, expanded.has(task.id) ? '▾' : '▸')
      : '',
    el('span', { class: isRoot ? 'fw-semibold' : '' }, task.name)
  );

  const subTotalOk = Math.abs(Number(task.children_weight_total ?? 100) - 100) < 0.01;
  const weightCell = el('td', { class: 'text-end' },
    task.weight + '%',
    task.is_leaf
      ? ''
      : el('div', { class: 'small ' + (subTotalOk ? 'text-muted' : 'text-danger') },
          'Sub: ' + task.children_weight_total + '%')
  );

  const actions = el('td', { class: 'text-end text-nowrap' });

  if (task.is_leaf) {
    actions.append(el('button', {
      class: 'btn btn-sm btn-outline-dark me-1',
      onclick: () => openExpenses(task),
    }, 'Biaya'));
  }

  if (canEdit) {
    if (isRoot) {
      actions.append(el('button', {
        class: 'btn btn-sm btn-outline-primary me-1',
        onclick: () => openTaskModal(null, task.id),
      }, '+ Sub'));

      if (children.length) {
        actions.append(el('button', {
          class: 'btn btn-sm btn-outline-primary me-1',
          onclick: () => openWeights(task.id, children, 'Bobot subaktivitas: ' + task.name),
        }, 'Bobot sub'));
      }
    }
    actions.append(
      el('button', { class: 'btn btn-sm btn-outline-secondary me-1', onclick: () => openTaskModal(task, null) }, 'Edit'),
      el('button', { class: 'btn btn-sm btn-outline-danger', onclick: () => deleteTask(task) }, 'Hapus')
    );
  }

  return el('tr', {},
    nameCell,
    el('td', { class: 'text-muted' }, task.pic_name || '-'),
    weightCell,
    el('td', { class: task.is_overdue ? 'text-danger' : '' }, formatDate(task.target_end)),
    el('td', {}, badge(statusLabel, statusClass), task.is_overdue ? ' ' : '', task.is_overdue ? badge('Terlambat', 'bg-danger-subtle text-danger-emphasis') : ''),
    budgetCells(task),
    el('td', {}, progressCell(task)),
    actions
  );
}

async function saveProgress(task, value) {
  clearAlert();

  try {
    await api('/tasks/' + task.id, { method: 'PUT', body: { progress: Number(value) } });
  } catch (error) {
    showAlert(formatError(error));
  }

  await load();
}

async function deleteTask(task) {
  const extra = (task.children || []).length ? ' beserta subaktivitasnya' : '';

  if (!confirm('Hapus "' + task.name + '"' + extra + '?')) {
    return;
  }

  clearAlert();

  try {
    await api('/tasks/' + task.id, { method: 'DELETE' });
  } catch (error) {
    showAlert(formatError(error));
  }

  await load();
}

function openTaskModal(task, parentId) {
  editingId = task ? task.id : null;
  parentForCreate = parentId;

  document.getElementById('task-title').textContent = task
    ? 'Ubah tugas'
    : (parentId ? 'Tambah subaktivitas' : 'Tambah aktivitas');

  document.getElementById('form-alert').classList.add('d-none');
  document.getElementById('t-name').value = task ? task.name : '';
  document.getElementById('t-pic').value = task ? (task.pic_name || '') : currentUser().name;
  document.getElementById('t-weight').value = task ? task.weight : 0;
  document.getElementById('t-priority').value = task ? task.priority : 'normal';
  document.getElementById('t-status').value = task ? task.status : 'not_started';
  document.getElementById('t-start').value = task ? (task.planned_start || '') : '';
  document.getElementById('t-end').value = task ? (task.target_end || '') : '';
  document.getElementById('t-budget').value = task ? task.budget_plan : 0;
  document.getElementById('t-notes').value = task ? (task.notes || '') : '';

  const budgetLocked = task ? !task.is_leaf : false;
  document.getElementById('t-budget').disabled = budgetLocked;
  document.getElementById('t-budget-help').textContent = budgetLocked
    ? 'Dihitung otomatis dari subaktivitas.'
    : 'Hanya untuk tugas tanpa subaktivitas.';

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-task')).show();
}

async function saveTask(event) {
  event.preventDefault();

  const formAlert = document.getElementById('form-alert');
  const button = document.getElementById('btn-save');
  formAlert.classList.add('d-none');
  button.disabled = true;

  const body = {
    name: document.getElementById('t-name').value,
    pic_name: document.getElementById('t-pic').value,
    weight: Number(document.getElementById('t-weight').value || 0),
    priority: document.getElementById('t-priority').value,
    status: document.getElementById('t-status').value,
    planned_start: document.getElementById('t-start').value,
    target_end: document.getElementById('t-end').value,
    notes: document.getElementById('t-notes').value,
  };

  if (!document.getElementById('t-budget').disabled) {
    body.budget_plan = Number(document.getElementById('t-budget').value || 0);
  }

  try {
    if (editingId) {
      await api('/tasks/' + editingId, { method: 'PUT', body });
    } else {
      body.parent_task_id = parentForCreate;
      await api('/projects/' + projectId + '/tasks', { method: 'POST', body });

      if (parentForCreate) {
        expanded.add(parentForCreate);
      }
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-task')).hide();
    await load();
  } catch (error) {
    formAlert.textContent = formatError(error);
    formAlert.classList.remove('d-none');
  } finally {
    button.disabled = false;
  }
}

async function openExpenses(task) {
  expenseTask = task;

  document.getElementById('exp-title').textContent = 'Biaya: ' + task.name;
  document.getElementById('exp-alert').classList.add('d-none');
  document.getElementById('exp-form').classList.toggle('d-none', !canEdit);
  document.getElementById('e-date').value = new Date().toLocaleDateString('en-CA');

  await loadExpenses();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-expense')).show();
}

async function loadExpenses() {
  const box = document.getElementById('exp-alert');

  try {
    const result = await api('/tasks/' + expenseTask.id + '/expenses');

    const rows = result.data.map((expense) => el('tr', {},
      el('td', {}, formatDate(expense.expense_date)),
      el('td', {}, expense.description),
      el('td', { class: 'text-end' }, formatRupiah(expense.amount)),
      el('td', { class: 'text-end' }, canEdit
        ? el('button', { class: 'btn btn-sm btn-outline-danger', onclick: () => deleteExpense(expense) }, 'Hapus')
        : '')
    ));

    document.getElementById('exp-rows').replaceChildren(...rows);
    document.getElementById('exp-empty').classList.toggle('d-none', rows.length > 0);
  } catch (error) {
    box.textContent = formatError(error);
    box.classList.remove('d-none');
  }
}

async function saveExpense(event) {
  event.preventDefault();

  const box = document.getElementById('exp-alert');
  box.classList.add('d-none');

  try {
    await api('/tasks/' + expenseTask.id + '/expenses', {
      method: 'POST',
      body: {
        expense_date: document.getElementById('e-date').value,
        description: document.getElementById('e-desc').value,
        amount: Number(document.getElementById('e-amount').value),
      },
    });

    document.getElementById('e-desc').value = '';
    document.getElementById('e-amount').value = '';
    await loadExpenses();
    await load();
  } catch (error) {
    box.textContent = formatError(error);
    box.classList.remove('d-none');
  }
}

async function deleteExpense(expense) {
  if (!confirm('Hapus biaya "' + expense.description + '"?')) {
    return;
  }

  try {
    await api('/expenses/' + expense.id, { method: 'DELETE' });
    await loadExpenses();
    await load();
  } catch (error) {
    const box = document.getElementById('exp-alert');
    box.textContent = formatError(error);
    box.classList.remove('d-none');
  }
}

function openWeights(parentId, items, title) {
  weightGroup = { parentId };
  document.getElementById('w-title').textContent = title;
  document.getElementById('w-alert').classList.add('d-none');

  document.getElementById('w-list').replaceChildren(...items.map((item) => {
    const input = el('input', {
      type: 'number', min: '0', max: '100', step: '0.01',
      class: 'form-control form-control-sm text-end', style: 'width: 110px;',
      value: String(item.weight), 'data-id': String(item.id), 'aria-label': 'Bobot ' + item.name,
    });
    input.addEventListener('input', updateWeightTotal);

    return el('div', { class: 'd-flex justify-content-between align-items-center mb-2' },
      el('span', {}, item.name),
      el('div', { class: 'd-flex align-items-center gap-1' }, input, '%')
    );
  }));

  updateWeightTotal();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-weights')).show();
}

function currentWeights() {
  return [...document.querySelectorAll('#w-list input')].map((input) => ({
    id: Number(input.dataset.id),
    weight: Number(input.value || 0),
  }));
}

function updateWeightTotal() {
  const total = Math.round(currentWeights().reduce((sum, item) => sum + item.weight, 0) * 100) / 100;
  const ok = Math.abs(total - 100) < 0.01;
  const box = document.getElementById('w-total');

  box.textContent = 'Total: ' + total + '%';
  box.className = 'fw-semibold mt-3 ' + (ok ? 'text-success' : 'text-danger');
  document.getElementById('w-save').disabled = !ok;
}

async function saveWeights() {
  const box = document.getElementById('w-alert');
  box.classList.add('d-none');

  try {
    await api('/projects/' + projectId + '/weights', {
      method: 'PUT',
      body: { parent_task_id: weightGroup.parentId, weights: currentWeights() },
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-weights')).hide();
    await load();
  } catch (error) {
    box.textContent = formatError(error);
    box.classList.remove('d-none');
  }
}

function renderWorkflow() {
  const user = currentUser();
  const isOwner = user.role === 'project_manager' && project.created_by === user.id;
  const buttons = [];

  if (isOwner && project.status === 'perencanaan') {
    buttons.push(el('button', {
      class: 'btn btn-success btn-sm',
      onclick: () => runWorkflow('activate', 'Aktifkan proyek ini?'),
    }, 'Aktifkan proyek'));
  }

  if (isOwner && ['berjalan', 'revisi'].includes(project.status)) {
    buttons.push(el('button', {
      class: 'btn btn-warning btn-sm',
      onclick: () => runWorkflow('submit', 'Ajukan proyek ke tester? Setelah diajukan, proyek tidak bisa diubah sampai selesai diperiksa.'),
    }, 'Ajukan ke tester'));
  }

  if (user.role === 'tester' && project.status === 'diajukan') {
    buttons.push(el('button', { class: 'btn btn-primary btn-sm', onclick: openReview }, 'Beri review'));
  }

  document.getElementById('workflow-actions').replaceChildren(...buttons);

  const banner = document.getElementById('review-banner');
  const last = reviews[0];

  if (project.status === 'revisi' && last && last.decision === 'keluhan') {
    banner.textContent = 'Catatan tester (' + last.reviewer + '): ' + last.notes;
    banner.classList.remove('d-none');
  } else {
    banner.classList.add('d-none');
  }

  document.getElementById('review-list').replaceChildren(...reviews.map((review) =>
    el('div', { class: 'border-bottom py-2' },
      badge(review.decision_label, review.decision === 'lulus'
        ? 'bg-success-subtle text-success-emphasis'
        : 'bg-danger-subtle text-danger-emphasis'),
      ' ',
      el('span', { class: 'text-muted' }, (review.reviewer || '-') + ' · ' + new Date(review.created_at).toLocaleString('id-ID')),
      review.notes ? el('div', { class: 'mt-1' }, review.notes) : ''
    )
  ));
  document.getElementById('review-empty').classList.toggle('d-none', reviews.length > 0);
}

async function runWorkflow(action, question) {
  if (!confirm(question)) {
    return;
  }

  clearAlert();

  try {
    await api('/projects/' + projectId + '/' + action, { method: 'POST' });
  } catch (error) {
    const problems = (error.errors && error.errors.project) || [];
    showAlert(problems.length ? 'Belum bisa: ' + problems.join(' ') : error.message);
  }

  await load();
}

function openReview() {
  document.getElementById('r-alert').classList.add('d-none');
  document.getElementById('r-decision').value = 'lulus';
  document.getElementById('r-notes').value = '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-review')).show();
}

async function saveReview(event) {
  event.preventDefault();

  const box = document.getElementById('r-alert');
  box.classList.add('d-none');

  try {
    await api('/projects/' + projectId + '/review', {
      method: 'POST',
      body: {
        decision: document.getElementById('r-decision').value,
        notes: document.getElementById('r-notes').value,
      },
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-review')).hide();
    await load();
  } catch (error) {
    box.textContent = formatError(error);
    box.classList.remove('d-none');
  }
}

if (requireLogin()) {
  renderSidebar('projects');
  document.getElementById('btn-add-root').addEventListener('click', () => openTaskModal(null, null));
  document.getElementById('btn-weights-root').addEventListener('click', () => openWeights(null, tasks, 'Bobot aktivitas utama'));
  document.getElementById('task-form').addEventListener('submit', saveTask);
  document.getElementById('exp-form').addEventListener('submit', saveExpense);
  document.getElementById('w-save').addEventListener('click', saveWeights);
  document.getElementById('review-form').addEventListener('submit', saveReview);
  load();
}