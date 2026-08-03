// Base URL of the PHP API. Defaults to /api (same-domain deployment, api/ served from public/api/).
// For a split deployment (frontend and backend on different domains), set this before api.js loads:
//   <script>window.SUBPASS_API_BASE = "https://your-backend.example.com/api";</script>
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
    const err = new Error(data.error || 'Something went wrong. Please try again.');
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

const Api = {
  register: (payload) => apiFetch('/auth.php?action=register', { method: 'POST', body: JSON.stringify(payload) }),
  login: (payload) => apiFetch('/auth.php?action=login', { method: 'POST', body: JSON.stringify(payload) }),
  logout: () => apiFetch('/auth.php?action=logout', { method: 'POST' }),
  me: () => apiFetch('/auth.php?action=me'),

  products: (params = '') => apiFetch(`/products.php${params}`),
  product: (idOrSlugParam) => apiFetch(`/products.php${idOrSlugParam}`),

  plans: () => apiFetch('/plans.php'),
  plan: (param) => apiFetch(`/plans.php${param}`),

  cart: {
    list: () => apiFetch('/cart.php'),
    add: (type, id, quantity = 1) => apiFetch('/cart.php', { method: 'POST', body: JSON.stringify({ type, id, quantity }) }),
    update: (cartItemId, quantity) => apiFetch(`/cart.php?id=${cartItemId}`, { method: 'PUT', body: JSON.stringify({ quantity }) }),
    remove: (cartItemId) => apiFetch(`/cart.php?id=${cartItemId}`, { method: 'DELETE' }),
    clear: () => apiFetch('/cart.php', { method: 'DELETE' }),
  },

  orders: {
    list: () => apiFetch('/orders.php'),
    get: (id) => apiFetch(`/orders.php?id=${id}`),
    create: (payload) => apiFetch('/orders.php', { method: 'POST', body: JSON.stringify(payload) }),
  },

  payment: {
    init: (orderId) => apiFetch('/payment/init.php', { method: 'POST', body: JSON.stringify({ order_id: orderId }) }),
    manual: (payload) => apiFetch('/payment/manual.php', { method: 'POST', body: JSON.stringify(payload) }),
  },

  // Generic helpers
  post: (path, payload) => apiFetch(path.replace(/^\/api/, ''), { method: 'POST', body: JSON.stringify(payload) }),

  passes: () => apiFetch('/passes.php'),
};
