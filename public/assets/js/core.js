/* =========================================================
   DexxoxxBD core — API client, safe DOM helpers, motion layer.
   Loaded by every page before the page-specific script.
   No inline scripts are used anywhere, so this file is the only
   place that touches the API or the DOM.
   ========================================================= */
(function (window, document) {
  'use strict';

  // Relative to the page: /public/*.html -> /public/api/, /public/admin/*.html
  // sets DEXXOXX_API_BASE to "../api".
  const API_BASE = window.DEXXOXX_API_BASE || 'api';
  const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let csrfToken = null;

  /* ---------------- API ---------------- */
  async function rawRequest(path, options) {
    const response = await fetch(API_BASE + path, Object.assign({
      method: 'GET',
      credentials: 'include',
      headers: Object.assign({ 'Content-Type': 'application/json' }, options && options.headers),
    }, options || {}));

    let data = {};
    try { data = await response.json(); } catch (err) { data = {}; }

    if (!response.ok) {
      const error = new Error(data.error || 'Something went wrong. Please try again.');
      error.status = response.status;
      error.fields = data.errors || {};
      throw error;
    }

    return data;
  }

  async function api(path, options) {
    const settings = Object.assign({ method: 'GET', body: null, headers: null }, options || {});
    const unsafe = settings.method !== 'GET';

    if (unsafe && !csrfToken) {
      const issued = await rawRequest('/auth.php?action=token');
      csrfToken = issued.csrf_token || null;
    }

    settings.headers = Object.assign(
      { 'Content-Type': 'application/json' },
      unsafe ? { 'X-CSRF-Token': csrfToken || '' } : {},
      (options && options.headers) || {}
    );

    if (settings.body !== null && settings.body !== undefined && typeof settings.body !== 'string') {
      settings.body = JSON.stringify(settings.body);
    }

    try {
      const data = await rawRequest(path, settings);
      if (data && data.csrf_token) csrfToken = data.csrf_token;
      return data;
    } catch (error) {
      // A rotated or expired session answers 419 once: mint a new token and retry.
      if (error.status === 419 && unsafe && !settings._retried) {
        csrfToken = null;
        const retry = Object.assign({}, options, { _retried: true });
        const fresh = await rawRequest('/auth.php?action=token');
        csrfToken = fresh.csrf_token || null;
        return api(path, retry);
      }
      throw error;
    }
  }

  const Api = {
    get: (path) => api(path),
    post: (path, body) => api(path, { method: 'POST', body: body }),
    put: (path, body) => api(path, { method: 'PUT', body: body }),
    del: (path, body) => api(path, { method: 'DELETE', body: body }),
    token: () => api('/auth.php?action=token'),
  };

  const Endpoints = {
    auth: {
      me: () => Api.get('/auth.php?action=me'),
      login: (payload) => Api.post('/auth.php?action=login', payload),
      register: (payload) => Api.post('/auth.php?action=register', payload),
      logout: () => Api.post('/auth.php?action=logout'),
    },
    products: {
      list: (params) => Api.get('/products.php' + (params || '')),
      get: (params) => Api.get('/products.php' + (params || '')),
      create: (body) => Api.post('/products.php', body),
      update: (id, body) => Api.put('/products.php?id=' + id, body),
      remove: (id) => Api.del('/products.php?id=' + id),
    },
    plans: {
      list: () => Api.get('/plans.php'),
      create: (body) => Api.post('/plans.php', body),
      update: (id, body) => Api.put('/plans.php?id=' + id, body),
      remove: (id) => Api.del('/plans.php?id=' + id),
    },
    categories: () => Api.get('/categories.php'),
    settings: { public: () => Api.get('/settings.php'), admin: () => Api.get('/admin_settings.php'), save: (body) => Api.put('/admin_settings.php', body) },
    cart: {
      list: () => Api.get('/cart.php'),
      add: (body) => Api.post('/cart.php', body),
      update: (id, body) => Api.put('/cart.php?id=' + id, body),
      remove: (id) => Api.del('/cart.php?id=' + id),
      clear: () => Api.del('/cart.php'),
    },
    orders: {
      list: () => Api.get('/orders.php'),
      get: (id) => Api.get('/orders.php?id=' + id),
      create: (body) => Api.post('/orders.php', body),
    },
    passes: () => Api.get('/passes.php'),
    payment: {
      methods: () => Api.get('/payment/methods.php'),
      init: (orderId) => Api.post('/payment/init.php', { order_id: orderId }),
      manual: (body) => Api.post('/payment/manual.php', body),
    },
    admin: {
      stats: () => Api.get('/admin_stats.php'),
      sweep: () => Api.post('/admin_stats.php', {}),
      orders: (params) => Api.get('/admin_orders.php' + (params || '')),
      order: (id) => Api.get('/admin_orders.php?id=' + id),
      setOrderStatus: (id, status) => Api.put('/admin_orders.php?id=' + id, { status: status }),
      users: (params) => Api.get('/users.php' + (params || '')),
      setUser: (id, body) => Api.put('/users.php?id=' + id, body),
      manualPayments: (params) => Api.get('/admin_manual_payments.php' + (params || '')),
      reviewPayment: (id, body) => Api.put('/admin_manual_payments.php?id=' + id, body),
      passes: (params) => Api.get('/admin_passes.php' + (params || '')),
      revokePass: (id) => Api.put('/admin_passes.php?id=' + id, {}),
    },
  };

  /* ---------------- Safe DOM building ---------------- */
  // Nothing that comes from the API is ever assigned to innerHTML: text goes
  // through createTextNode, so a stored <script> in a product name is inert.
  function h(tag, attributes) {
    const node = document.createElement(tag);

    if (attributes) {
      Object.keys(attributes).forEach(function (key) {
        const value = attributes[key];
        if (value === null || value === undefined || value === false) return;
        if (key === 'class') node.className = value;
        else if (key === 'text') node.textContent = String(value);
        else if (key === 'dataset') Object.assign(node.dataset, value);
        else if (key.slice(0, 2) === 'on' && typeof value === 'function') node.addEventListener(key.slice(2).toLowerCase(), value);
        else if (key === 'html') node.innerHTML = sanitize(value); // static markup only — never API data
        else node.setAttribute(key, String(value));
      });
    }

    for (let i = 2; i < arguments.length; i++) append(node, arguments[i]);
    return node;
  }

  function append(parent, child) {
    if (child === null || child === undefined || child === false) return;
    if (Array.isArray(child)) { child.forEach(function (item) { append(parent, item); }); return; }
    parent.appendChild(child instanceof Node ? child : document.createTextNode(String(child)));
  }

  // Only the markup we write ourselves reaches `html:`; strip anything else.
  function sanitize(markup) {
    const template = document.createElement('template');
    template.innerHTML = String(markup);
    template.content.querySelectorAll('script, style, iframe, object, embed, link, meta').forEach(function (node) { node.remove(); });
    template.content.querySelectorAll('*').forEach(function (node) {
      Array.prototype.slice.call(node.attributes).forEach(function (attribute) {
        if (/^on/i.test(attribute.name) || ((attribute.name === 'href' || attribute.name === 'src') && /^\s*javascript:/i.test(attribute.value))) {
          node.removeAttribute(attribute.name);
        }
      });
    });
    return template.innerHTML;
  }

  function clear(node) { while (node && node.firstChild) node.removeChild(node.firstChild); }
  function mount(idOrNode, content) {
    const target = typeof idOrNode === 'string' ? document.getElementById(idOrNode) : idOrNode;
    if (!target) return null;
    clear(target);
    append(target, content);
    return target;
  }

  /* ---------------- Formatting ---------------- */
  function money(value) {
    return '৳' + Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
  }

  function formatDate(value) {
    if (!value) return '—';
    return new Date(String(value).replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function formatDateTime(value) {
    if (!value) return '—';
    return new Date(String(value).replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  }

  function initials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase();
  }

  function daysBetween(from, to) {
    return Math.floor((new Date(to) - new Date(from)) / 86400000);
  }

  /* ---------------- Toasts ---------------- */
  function toast(message, kind) {
    let stack = document.querySelector('.toast-stack');
    if (!stack) { stack = h('div', { class: 'toast-stack', role: 'status', 'aria-live': 'polite' }); document.body.appendChild(stack); }

    const node = h('div', { class: 'toast' + (kind ? ' is-' + kind : ''), text: message });
    stack.appendChild(node);
    setTimeout(function () {
      node.classList.add('is-leaving');
      setTimeout(function () { node.remove(); }, 320);
    }, 3400);
  }

  /* ---------------- Motion ---------------- */
  function observeReveal(scope) {
    const nodes = (scope || document).querySelectorAll('.reveal, .reveal-scale, .reveal-left:not(.is-in)');
    if (REDUCED || !('IntersectionObserver' in window)) {
      nodes.forEach(function (node) { node.classList.add('is-in'); });
      return;
    }

    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });

    nodes.forEach(function (node, index) {
      if (!node.style.getPropertyValue('--i')) node.style.setProperty('--i', String(index % 12));
      observer.observe(node);
    });
  }

  function countUp(node, target, duration) {
    const end = Number(target || 0);
    if (REDUCED) { node.textContent = formatNumber(end); return; }

    const ms = duration || 900;
    const start = performance.now();
    function step(now) {
      const progress = Math.min(1, (now - start) / ms);
      const eased = 1 - Math.pow(1 - progress, 3);
      node.textContent = formatNumber(Math.round(end * eased));
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function formatNumber(value) { return Number(value).toLocaleString('en-US'); }

  function attachRipples(scope) {
    (scope || document).addEventListener('click', function (event) {
      const button = event.target.closest('.btn');
      if (!button || REDUCED) return;

      const rect = button.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const dot = h('span', { class: 'ripple' });
      dot.style.setProperty('--ripple-size', size + 'px');
      dot.style.setProperty('--ripple-x', (event.clientX - rect.left - size / 2) + 'px');
      dot.style.setProperty('--ripple-y', (event.clientY - rect.top - size / 2) + 'px');
      button.appendChild(dot);
      setTimeout(function () { dot.remove(); }, 640);
    });
  }

  function attachTilt(selector) {
    if (REDUCED || !window.matchMedia('(hover: hover)').matches) return;

    document.querySelectorAll(selector).forEach(function (card) {
      card.addEventListener('pointermove', function (event) {
        const rect = card.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        card.style.setProperty('--tilt-y', (x * 5).toFixed(2) + 'deg');
        card.style.setProperty('--tilt-x', (-y * 5).toFixed(2) + 'deg');
      });
      card.addEventListener('pointerleave', function () {
        card.style.setProperty('--tilt-y', '0deg');
        card.style.setProperty('--tilt-x', '0deg');
      });
    });
  }

  function skeletonCards(container, count, className) {
    const nodes = [];
    for (let i = 0; i < count; i++) nodes.push(h('div', { class: 'skeleton ' + (className || 'skeleton-tile') }));
    mount(container, nodes);
  }

  function skeletonRows(container, columns, rows) {
    const nodes = [];
    for (let r = 0; r < rows; r++) {
      const cells = [];
      for (let c = 0; c < columns; c++) {
        cells.push(h('td', null, h('div', { class: 'skeleton skeleton-cell' + (c === 0 ? ' skeleton-wide' : '') })));
      }
      nodes.push(h('tr', null, cells));
    }
    mount(container, nodes);
  }

  /* ---------------- Modal ---------------- */
  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return null;
    modal.classList.remove('hide');
    document.body.classList.add('is-locked');
    const focusable = modal.querySelector('input, select, textarea, button');
    if (focusable) setTimeout(function () { focusable.focus(); }, 60);
    return modal;
  }

  function closeModal(id) {
    const modal = typeof id === 'string' ? document.getElementById(id) : id;
    if (!modal) return;
    modal.classList.add('hide');
    if (!document.querySelector('.modal:not(.hide)')) document.body.classList.remove('is-locked');
  }

  function wireModalDismiss() {
    document.addEventListener('click', function (event) {
      if (event.target.classList && event.target.classList.contains('modal')) closeModal(event.target);
      if (event.target.closest && event.target.closest('[data-close-modal]')) closeModal(event.target.closest('[data-close-modal]').getAttribute('data-close-modal') || event.target.closest('.modal'));
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') document.querySelectorAll('.modal:not(.hide)').forEach(closeModal);
    });
  }

  /* ---------------- Field errors ---------------- */
  function applyFieldErrors(error) {
    document.querySelectorAll('.field.is-invalid').forEach(function (field) { field.classList.remove('is-invalid'); });
    document.querySelectorAll('.error-text').forEach(function (node) { node.textContent = ''; });

    if (!error || !error.fields) return;

    Object.keys(error.fields).forEach(function (name) {
      const input = document.querySelector('[name="' + name + '"]');
      if (!input) return;
      const field = input.closest('.field');
      if (field) field.classList.add('is-invalid');
      const slot = document.querySelector('.error-text[data-for="' + name + '"]');
      if (slot) slot.textContent = error.fields[name];
    });
  }

  function setBusy(button, busy, labelBusy) {
    if (!button) return;
    if (busy) {
      button.dataset.label = button.textContent;
      button.disabled = true;
      button.textContent = labelBusy || 'Working…';
    } else {
      button.disabled = false;
      if (button.dataset.label) button.textContent = button.dataset.label;
    }
  }

  /* ---------------- Shared page chrome ---------------- */
  async function currentUser() {
    try { return (await Endpoints.auth.me()).user; } catch (error) { return null; }
  }

  async function requireUser(redirect) {
    const user = await currentUser();
    if (!user) { location.href = redirect || 'login.html'; throw new Error('redirecting'); }
    return user;
  }

  async function requireAdmin(redirect) {
    const user = await currentUser();
    if (!user) { location.href = redirect || 'index.html'; throw new Error('redirecting'); }
    if (user.role !== 'admin') { location.href = redirect || '../login.html'; throw new Error('redirecting'); }
    return user;
  }

  async function refreshCartBadge() {
    const badge = document.getElementById('cart-badge');
    if (!badge) return;
    try {
      const cart = await Api.get('/cart.php');
      badge.textContent = String(cart.count);
      badge.classList.toggle('hide', !cart.count);
      badge.classList.add('pop');
      setTimeout(function () { badge.classList.remove('pop'); }, 320);
    } catch (error) {
      badge.classList.add('hide');
    }
  }

  function renderAuthArea(user) {
    const slot = document.getElementById('nav-auth-area');
    if (!slot) return;

    clear(slot);

    if (user) {
      slot.append(
        h('a', { class: 'chip', href: user.role === 'admin' ? 'admin/dashboard.html' : 'dashboard.html' }, 'My passes'),
        h('button', { class: 'btn btn-ghost btn-sm', id: 'nav-logout' }, 'Sign out')
      );
      const logout = document.getElementById('nav-logout');
      logout.addEventListener('click', async function () {
        try { await Endpoints.auth.logout(); } catch (error) { /* already signed out */ }
        location.href = 'index.html';
      });
    } else {
      slot.append(
        h('a', { class: 'btn btn-ghost btn-sm', href: 'login.html' }, 'Sign in'),
        h('a', { class: 'btn btn-primary btn-sm', href: 'register.html' }, 'Get started')
      );
    }

    const adminLink = document.getElementById('nav-admin-link');
    if (adminLink) adminLink.classList.toggle('hide', !user || user.role !== 'admin');
  }

  function markActiveNav() {
    const page = location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.nav-links a').forEach(function (link) {
      if (link.getAttribute('href') === page) link.setAttribute('aria-current', 'page');
    });
  }

  function queryParam(name) {
    return new URLSearchParams(location.search).get(name);
  }

  // Only allow same-origin, non-protocol paths — kills ?redirect=//evil and javascript:.
  function safeRedirect(target, fallback) {
    const path = String(target || '');
    const ok = path[0] === '/' ? !/^\/\//.test(path) && !/^\/\\/.test(path) : /^(?!.*:\/\/)[A-Za-z0-9._\-/?=&#%]+$/.test(path);
    return ok ? path : fallback;
  }

  /* ---------------- Shared cards ---------------- */
  function addToCart(type, id, button) {
    setBusy(button, true, 'Added');
    Endpoints.cart.add({ type: type, id: id })
      .then(function () {
        toast(type === 'plan' ? 'Pass added to your cart.' : 'Added to cart.', 'success');
        refreshCartBadge();
      })
      .catch(function (error) {
        toast(error.message, 'error');
        setBusy(button, false);
      });
  }

  function productCard(product, onAdd) {
    const media = h('div', { class: 'tile-media' });
    if (product.image_url) media.appendChild(h('img', { src: product.image_url, alt: '', loading: 'lazy' }));
    if (product.discount_percent) media.appendChild(h('span', { class: 'pill tile-flag', text: '-' + product.discount_percent + '%' }));
    if (product.is_featured) media.appendChild(h('span', { class: 'pill pill-teal tile-fav' }, '★ Featured'));

    const stockStamp = product.in_stock
      ? h('span', { class: 'stamp stamp-faint', text: product.duration_days + ' days' })
      : h('span', { class: 'stamp stamp-danger' }, 'Sold out');

    const addButton = h('button', {
      class: 'btn btn-primary btn-sm',
      type: 'button',
      disabled: !product.in_stock,
    }, product.in_stock ? 'Add to cart' : 'Notify me');

    if (onAdd) {
      addButton.addEventListener('click', function () { onAdd(product, addButton); });
    }

    return h('article', { class: 'card card-hover tile reveal' },
      h('a', { href: 'product-detail.html?slug=' + encodeURIComponent(product.slug), 'aria-label': product.name }, media),
      h('div', { class: 'tile-body' },
        h('h3', { class: 'tile-title' }, h('a', { href: 'product-detail.html?slug=' + encodeURIComponent(product.slug) }, product.name)),
        h('p', { class: 'tile-desc' }, product.description || ''),
        h('div', { class: 'cluster' }, stockStamp)
      ),
      h('div', { class: 'tile-foot' },
        h('div', null,
          h('div', { class: 'cluster' },
            h('span', { class: 'price', text: money(product.price) }),
            product.compare_at_price ? h('span', { class: 'price-old', text: money(product.compare_at_price) }) : null
          ),
          h('span', { class: 'tile-meta mono', text: (product.category || 'Digital') + ' · ' + product.duration_days + 'd' })
        ),
        addButton
      )
    );
  }

  function planCard(plan, onAdd) {
    const features = h('ul', { class: 'plan-features' });
    (plan.features || []).forEach(function (feature) {
      features.appendChild(h('li', { class: 'cluster' },
        h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.5', 'stroke-linecap': 'round', 'aria-hidden': 'true' }, h('path', { d: 'M20 6L9 17l-5-5' })),
        h('span', { text: feature })
      ));
    });

    const button = h('button', { class: 'btn btn-primary btn-block', type: 'button' }, 'Choose ' + plan.name);
    if (onAdd) button.addEventListener('click', function () { onAdd(plan, button); });

    return h('article', { class: 'card plan-card reveal' + (plan.badge === 'Most Popular' ? ' is-featured' : '') },
      plan.badge ? h('span', { class: 'pill tile-flag' , text: plan.badge }) : null,
      h('h3', { class: 'display' }, plan.name),
      h('p', { class: 'muted', text: plan.description || '' }),
      h('div', { class: 'plan-price' },
        h('span', { class: 'amount', text: money(plan.price) }),
        h('span', { class: 'period', text: plan.period_label })
      ),
      features,
      button
    );
  }

  window.Dx = {
    api: Api,
    endpoints: Endpoints,
    h: h,
    mount: mount,
    clear: clear,
    money: money,
    formatDate: formatDate,
    formatDateTime: formatDateTime,
    formatNumber: formatNumber,
    initials: initials,
    daysBetween: daysBetween,
    toast: toast,
    reveal: observeReveal,
    countUp: countUp,
    tilt: attachTilt,
    skeletonCards: skeletonCards,
    skeletonRows: skeletonRows,
    openModal: openModal,
    closeModal: closeModal,
    fieldErrors: applyFieldErrors,
    setBusy: setBusy,
    productCard: productCard,
    planCard: planCard,
    addToCart: addToCart,
    currentUser: currentUser,
    requireUser: requireUser,
    requireAdmin: requireAdmin,
    refreshCartBadge: refreshCartBadge,
    renderAuthArea: renderAuthArea,
    markActiveNav: markActiveNav,
    queryParam: queryParam,
    safeRedirect: safeRedirect,
    reducedMotion: REDUCED,
  };

  document.addEventListener('DOMContentLoaded', function () {
    markActiveNav();
    observeReveal();
    attachRipples(document);
    wireModalDismiss();
  });
})(window, document);
