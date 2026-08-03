// Defaults to /api (same-domain deployment). Override with window.SUBPASS_API_BASE for split hosting.
const API_BASE = window.SUBPASS_API_BASE || '/api';

async function apiFetch(path, options = {}) {
  const res = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });
  let data;
  try { data = await res.json(); } catch { data = {}; }
  if (!res.ok) {
    const err = new Error(data.error || 'Something went wrong.');
    err.status = res.status;
    throw err;
  }
  return data;
}

const AdminApi = {
  me: () => apiFetch('/auth.php?action=me'),
  login: (payload) => apiFetch('/auth.php?action=login', { method: 'POST', body: JSON.stringify(payload) }),
  logout: () => apiFetch('/auth.php?action=logout', { method: 'POST' }),

  stats: () => apiFetch('/admin_stats.php'),

  products: {
    list: () => apiFetch('/products.php'),
    create: (p) => apiFetch('/products.php', { method: 'POST', body: JSON.stringify(p) }),
    update: (id, p) => apiFetch(`/products.php?id=${id}`, { method: 'PUT', body: JSON.stringify(p) }),
    remove: (id) => apiFetch(`/products.php?id=${id}`, { method: 'DELETE' }),
  },

  plans: {
    list: () => apiFetch('/plans.php'),
    create: (p) => apiFetch('/plans.php', { method: 'POST', body: JSON.stringify(p) }),
    update: (id, p) => apiFetch(`/plans.php?id=${id}`, { method: 'PUT', body: JSON.stringify(p) }),
    remove: (id) => apiFetch(`/plans.php?id=${id}`, { method: 'DELETE' }),
  },

  orders: {
    list: (status = '') => apiFetch(`/admin_orders.php${status ? '?status=' + status : ''}`),
    get: (id) => apiFetch(`/admin_orders.php?id=${id}`),
    updateStatus: (id, status) => apiFetch(`/admin_orders.php?id=${id}`, { method: 'PUT', body: JSON.stringify({ status }) }),
  },

  users: {
    list: () => apiFetch('/users.php'),
    update: (id, payload) => apiFetch(`/users.php?id=${id}`, { method: 'PUT', body: JSON.stringify(payload) }),
  },

  manualPayments: {
    list: (status = '') => apiFetch(`/admin_manual_payments.php${status ? '?status=' + status : ''}`),
    review: (id, action, adminNote = '') =>
      apiFetch(`/admin_manual_payments.php?id=${id}`, {
        method: 'PUT',
        body: JSON.stringify({ action, admin_note: adminNote }),
      }),
  },
};

/** Guards every admin page: redirects non-admins to the admin login screen. */
async function requireAdminAuth() {
  try {
    const { user } = await AdminApi.me();
    if (user.role !== 'admin') throw new Error('not admin');
    document.querySelectorAll('.admin-user-name').forEach(el => el.textContent = user.name);
    return user;
  } catch {
    if (!location.pathname.endsWith('/admin/index.html') && !location.pathname.endsWith('/admin/')) {
      location.href = 'index.html';
    }
    throw new Error('redirecting');
  }
}

function adminToast(message, type = 'info') {
  const wrap = document.getElementById('toast-wrap');
  if (!wrap) return alert(message);
  const colors = { info: 'bg-navy text-white', error: 'bg-danger text-white', success: 'bg-teal text-white' };
  const el = document.createElement('div');
  el.className = `${colors[type]} px-4 py-3 rounded-lg shadow-lg font-medium text-sm max-w-xs`;
  el.textContent = message;
  wrap.appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

function money(n) { return 'Tk ' + Number(n).toLocaleString('en-BD', { maximumFractionDigits: 2 }); }
function fmtDate(d) { return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }); }
