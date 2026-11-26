const ROLE_BADGE = {
  project_manager: 'bg-primary-subtle text-primary-emphasis',
  tester: 'bg-info-subtle text-info-emphasis',
  operator: 'bg-dark-subtle text-dark-emphasis',
};

let users = [];
let editing = null;

function showAlert(message) {
  const box = document.getElementById('alert');
  box.textContent = message;
  box.classList.remove('d-none');
}

function formatError(error) {
  return Object.values(error.errors || {}).flat().join(' ') || error.message;
}

async function loadUsers() {
  document.getElementById('alert').classList.add('d-none');

  try {
    const result = await api('/users');
    users = result.data;
    render();
  } catch (error) {
    showAlert(error.message);
  }
}

function render() {
  const me = currentUser();

  const rows = users.map((user) => {
    const isSelf = user.id === me.id;

    return el('tr', {},
      el('td', { class: 'fw-semibold' }, user.name, isSelf ? ' (kamu)' : ''),
      el('td', { class: 'text-muted' }, user.email),
      el('td', {}, el('span', { class: 'badge rounded-pill ' + (ROLE_BADGE[user.role] || 'bg-secondary-subtle') }, user.role_label)),
      el('td', {}, el('span', {
        class: 'badge rounded-pill ' + (user.is_active ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'),
      }, user.is_active ? 'Aktif' : 'Nonaktif')),
      el('td', { class: 'text-end text-nowrap' },
        el('button', { class: 'btn btn-sm btn-outline-secondary me-1', onclick: () => openModal(user) }, 'Edit'),
        isSelf ? '' : el('button', {
          class: 'btn btn-sm ' + (user.is_active ? 'btn-outline-danger' : 'btn-outline-success'),
          onclick: () => toggleActive(user),
        }, user.is_active ? 'Nonaktifkan' : 'Aktifkan')
      )
    );
  });

  document.getElementById('rows').replaceChildren(...rows);
}

function openModal(user) {
  editing = user;
  const isSelf = user ? user.id === currentUser().id : false;

  document.getElementById('u-title').textContent = user ? 'Ubah pengguna' : 'Tambah pengguna';
  document.getElementById('u-alert').classList.add('d-none');
  document.getElementById('u-name').value = user ? user.name : '';
  document.getElementById('u-email').value = user ? user.email : '';
  document.getElementById('u-role').value = user ? user.role : 'project_manager';
  document.getElementById('u-role').disabled = isSelf;
  document.getElementById('u-role-help').classList.toggle('d-none', !isSelf);
  document.getElementById('u-password').value = '';
  document.getElementById('u-password').required = !user;
  document.getElementById('u-password-help').textContent = user
    ? 'Kosongkan kalau password tidak diubah. Kalau diisi: minimal 8 karakter, ada huruf besar, huruf kecil, dan angka.'
    : 'Minimal 8 karakter, ada huruf besar, huruf kecil, dan angka.';

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-user')).show();
}

async function saveUser(event) {
  event.preventDefault();

  const box = document.getElementById('u-alert');
  const button = document.getElementById('u-save');
  box.classList.add('d-none');
  button.disabled = true;

  const body = {
    name: document.getElementById('u-name').value,
    email: document.getElementById('u-email').value,
  };

  const password = document.getElementById('u-password').value;

  if (password) {
    body.password = password;
  }

  if (!document.getElementById('u-role').disabled) {
    body.role = document.getElementById('u-role').value;
  }

  try {
    if (editing) {
      await api('/users/' + editing.id, { method: 'PUT', body });
    } else {
      await api('/users', { method: 'POST', body });
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-user')).hide();
    await loadUsers();
  } catch (error) {
    box.textContent = formatError(error);
    box.classList.remove('d-none');
  } finally {
    button.disabled = false;
  }
}

async function toggleActive(user) {
  const question = user.is_active
    ? 'Nonaktifkan akun ' + user.name + '? Dia akan langsung keluar dan tidak bisa login.'
    : 'Aktifkan kembali akun ' + user.name + '?';

  if (!confirm(question)) {
    return;
  }

  try {
    await api('/users/' + user.id, { method: 'PUT', body: { is_active: !user.is_active } });
    await loadUsers();
  } catch (error) {
    showAlert(formatError(error));
  }
}

if (requireLogin()) {
  renderSidebar('users');
  document.getElementById('btn-add').addEventListener('click', () => openModal(null));
  document.getElementById('user-form').addEventListener('submit', saveUser);
  loadUsers();
}