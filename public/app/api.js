const API_BASE = '/api';

function getToken() {
  return sessionStorage.getItem('token');
}

async function api(path, options = {}) {
  const headers = { Accept: 'application/json' };
  const token = getToken();

  if (token) {
    headers.Authorization = 'Bearer ' + token;
  }

  if (options.body) {
    headers['Content-Type'] = 'application/json';
  }

  const response = await fetch(API_BASE + path, {
    method: options.method || 'GET',
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  if (response.status === 401 && path !== '/login') {
    sessionStorage.clear();
    window.location.href = '/app/login.html';
    throw new Error('Sesi berakhir, silakan login lagi.');
  }

  const data = response.status === 204 ? null : await response.json().catch(() => null);

  if (!response.ok) {
    const error = new Error((data && data.message) || 'Terjadi kesalahan.');
    error.status = response.status;
    error.errors = (data && data.errors) || {};
    throw error;
  }

  return data;
}