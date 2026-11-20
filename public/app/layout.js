function requireLogin() {
  if (!getToken()) {
    window.location.href = '/app/login.html';
    return false;
  }
  return true;
}

function currentUser() {
  return JSON.parse(sessionStorage.getItem('user') || 'null');
}

function el(tag, props = {}, ...children) {
  const node = document.createElement(tag);

  for (const [key, value] of Object.entries(props)) {
    if (key === 'class') {
      node.className = value;
    } else if (key.startsWith('on')) {
      node.addEventListener(key.slice(2), value);
    } else {
      node.setAttribute(key, value);
    }
  }

  node.append(...children.flat().map((child) => (child instanceof Node ? child : String(child ?? ''))));

  return node;
}

function formatDate(iso) {
  if (!iso) return '-';
  return new Date(iso + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatRupiah(value) {
  return 'Rp' + Number(value || 0).toLocaleString('id-ID');
}

async function logout() {
  try {
    await api('/logout', { method: 'POST' });
  } catch (error) {
    // abaikan, sesi tetap dibersihkan di bawah
  }
  sessionStorage.clear();
  window.location.href = '/app/login.html';
}

function renderSidebar(active) {
  const user = currentUser();
  const items = [{ key: 'projects', label: 'Proyek', href: '/app/projects.html' }];

  document.getElementById('sidebar').replaceChildren(
    el('div', { class: 'brand' }, 'AC Dikari'),
    el('nav', { class: 'nav flex-column gap-1' },
      items.map((item) => el('a', { class: 'nav-link' + (item.key === active ? ' active' : ''), href: item.href }, item.label))
    ),
    el('div', { class: 'mt-auto pt-3 border-top border-secondary' },
      el('div', { class: 'text-white fw-semibold' }, user.name),
      el('div', { class: 'small', style: 'color:#b8c7e0' }, user.role_label),
      el('button', { class: 'btn btn-sm btn-outline-light mt-2', onclick: logout }, 'Keluar')
    )
  );
}