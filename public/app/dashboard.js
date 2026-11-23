const STATUS = {
  perencanaan: 'bg-warning-subtle text-warning-emphasis',
  berjalan: 'bg-primary-subtle text-primary-emphasis',
  diajukan: 'bg-info-subtle text-info-emphasis',
  revisi: 'bg-danger-subtle text-danger-emphasis',
  selesai: 'bg-success-subtle text-success-emphasis',
};

function projectRow(project) {
  const percent = Math.round(project.progress);

  return el('div', { class: 'mb-3' },
    el('div', { class: 'd-flex justify-content-between align-items-center mb-1' },
      el('a', { class: 'text-decoration-none fw-semibold', href: '/app/project.html?id=' + project.id }, project.name),
      el('span', { class: 'badge rounded-pill ' + (STATUS[project.status] || 'bg-secondary-subtle') }, project.status_label)
    ),
    el('div', { class: 'd-flex align-items-center gap-2' },
      el('div', { class: 'progress flex-grow-1' },
        el('div', { class: 'progress-bar', role: 'progressbar', style: 'width:' + percent + '%' })),
      el('small', { class: 'fw-semibold' }, percent + '%')
    )
  );
}

function overdueRow(task) {
  return el('div', { class: 'border-bottom py-2' },
    el('a', { class: 'text-decoration-none fw-semibold', href: '/app/project.html?id=' + task.project_id }, task.name),
    el('div', { class: 'small text-muted' }, task.project_name + (task.pic_name ? ' · ' + task.pic_name : '')),
    el('div', { class: 'small text-danger' }, task.days_late + ' hari terlambat (deadline ' + formatDate(task.target_end) + ')')
  );
}

async function loadDashboard() {
  const alertBox = document.getElementById('alert');

  try {
    const { data } = await api('/dashboard');
    const byStatus = data.by_status || {};

    document.getElementById('d-total').textContent = data.total;
    document.getElementById('d-total-sub').textContent = (byStatus.berjalan || 0) + ' berjalan';
    document.getElementById('d-average').textContent = Math.round(data.average_progress) + '%';
    document.getElementById('d-overdue').textContent = data.overdue_count;
    document.getElementById('d-waiting').textContent = data.waiting_review;

    document.getElementById('d-projects').replaceChildren(...data.projects.map(projectRow));
    document.getElementById('d-projects-empty').classList.toggle('d-none', data.projects.length > 0);

    document.getElementById('d-overdue-list').replaceChildren(...data.overdue.map(overdueRow));
    document.getElementById('d-overdue-empty').classList.toggle('d-none', data.overdue.length > 0);
  } catch (error) {
    alertBox.textContent = error.message;
    alertBox.classList.remove('d-none');
  }
}

if (requireLogin()) {
  renderSidebar('dashboard');
  loadDashboard();
}