const STATUS = {
  perencanaan: ['Perencanaan', 'pill-perencanaan'],
  berjalan: ['Berjalan', 'pill-berjalan'],
  diajukan: ['Diajukan', 'pill-diajukan'],
  revisi: ['Revisi', 'pill-revisi'],
  selesai: ['Selesai', 'pill-selesai'],
};

let projects = [];

function openProject(project) {
  window.location.href = '/app/project.html?id=' + project.id;
}

function card(project) {
  const [label, pillClass] = STATUS[project.status] || [project.status, 'pill-perencanaan'];
  const progress = Math.round(project.progress ?? 0);
  const done = progress >= 100;

  return el('div', { class: 'col-md-6' },
    el('div', {
      class: 'card card-project h-100',
      role: 'link',
      tabindex: '0',
      onclick: () => openProject(project),
      onkeydown: (event) => { if (event.key === 'Enter') openProject(project); },
    },
      el('div', { class: 'card-body p-4' },
        el('div', { class: 'd-flex justify-content-between align-items-center mb-3' },
          el('div', { class: 'd-flex align-items-center gap-2' },
            el('div', { class: 'project-icon' }, project.name.charAt(0).toUpperCase()),
            el('span', { class: 'text-muted small' }, 'Proyek #' + project.id)
          ),
          el('span', { class: 'pill ' + pillClass }, label)
        ),
        el('h2', { class: 'h4 fw-bold mb-3' }, project.name),
        el('div', { class: 'd-flex justify-content-between small mb-1' },
          el('span', { class: 'text-muted' }, 'Progres proyek'),
          el('strong', { class: done ? 'text-success' : '' }, progress + '%')
        ),
        el('div', { class: 'progress mb-3' },
          el('div', { class: 'progress-bar' + (done ? ' bg-done' : ''), role: 'progressbar', style: 'width:' + progress + '%' })
        ),
        el('hr', { class: 'my-3' }),
        el('div', { class: 'small' },
          el('div', { class: 'text-muted' }, 'Deadline'),
          el('div', { class: 'fw-semibold' }, formatDate(project.target_end_date))
        )
      )
    )
  );
}

function render() {
  const keyword = document.getElementById('search').value.trim().toLowerCase();
  const status = document.getElementById('status-filter').value;

  const visible = projects.filter((project) =>
    project.name.toLowerCase().includes(keyword) && (status === '' || project.status === status)
  );

  document.getElementById('grid').replaceChildren(...visible.map(card));
  document.getElementById('empty').classList.toggle('d-none', visible.length > 0);
  document.getElementById('count').textContent = visible.length + ' proyek';

  const counts = {};
  projects.forEach((project) => { counts[project.status] = (counts[project.status] || 0) + 1; });
  document.getElementById('summary').textContent = Object.entries(counts)
    .map(([key, total]) => total + ' ' + (STATUS[key] ? STATUS[key][0].toLowerCase() : key))
    .join(' · ');
}

async function loadProjects() {
  const alertBox = document.getElementById('alert');
  alertBox.classList.add('d-none');

  try {
    const result = await api('/projects');
    projects = result.data;
    render();
  } catch (error) {
    alertBox.textContent = error.message;
    alertBox.classList.remove('d-none');
  }
}

async function saveProject(event) {
  event.preventDefault();

  const formAlert = document.getElementById('form-alert');
  const button = document.getElementById('btn-save');
  formAlert.classList.add('d-none');
  button.disabled = true;

  try {
    await api('/projects', {
      method: 'POST',
      body: {
        name: document.getElementById('f-name').value,
        description: document.getElementById('f-desc').value,
        start_date: document.getElementById('f-start').value,
        target_end_date: document.getElementById('f-end').value,
      },
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-project')).hide();
    document.getElementById('project-form').reset();
    await loadProjects();
  } catch (error) {
    formAlert.textContent = Object.values(error.errors || {}).flat().join(' ') || error.message;
    formAlert.classList.remove('d-none');
  } finally {
    button.disabled = false;
  }
}

if (requireLogin()) {
  renderSidebar('projects');

  document.getElementById('today').textContent =
    new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  if (currentUser().role === 'project_manager') {
    document.getElementById('btn-add').classList.remove('d-none');
  }

  document.getElementById('search').addEventListener('input', render);
  document.getElementById('status-filter').addEventListener('change', render);
  document.getElementById('project-form').addEventListener('submit', saveProject);
  loadProjects();
}