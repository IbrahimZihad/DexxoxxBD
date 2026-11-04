/* Admin chrome shared by every panel page: sidebar, identity guard, small renderers. */
(function (window, document) {
  'use strict';

  const SVG_NS = 'http://www.w3.org/2000/svg';

  const ICONS = {
    dashboard: ['M4 13h6V4H4v9zm0 7h6v-5H4v5zm10 0h6v-9h-6v9zm0-16v6h6V4h-6z'],
    products: ['M4 7h16M4 12h16M4 17h10'],
    orders: ['M7 4h10l2 4v11H5V8l2-4zm0 8h10'],
    passes: ['M4 8h16v3a2 2 0 000 4v3H4v-3a2 2 0 000-4V8zm7 0v10'],
    users: ['M16 19v-1a4 4 0 00-8 0v1M12 11a3 3 0 100-6 3 3 0 000 6z'],
    settings: ['M12 15a3 3 0 100-6 3 3 0 000 6zm7-3-2-.4-.6-1.5 1.2-1.5-2.1-2.1-1.5 1.2L12.7 6 12 4l-2.7.7-.6 1.6-1.5-1.2L5.1 7.2l1.2 1.5-.6 1.5L3 11l2 3'],
    wallet: ['M4 7h13a2 2 0 012 2v8H6a2 2 0 01-2-2V7zm10 5h3'],
    search: ['M11 19a8 8 0 100-16 8 8 0 000 16zm10-2-4.35-4.35'],
  };

  function icon(name) {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('aria-hidden', 'true');
    (ICONS[name] || ICONS.dashboard).forEach(function (d) {
      const path = document.createElementNS(SVG_NS, 'path');
      path.setAttribute('d', d);
      svg.appendChild(path);
    });
    return svg;
  }

  const NAV = [
    { href: 'dashboard.html', label: 'Overview', icon: 'dashboard', id: 'nav-overview' },
    { href: 'orders.html', label: 'Orders', icon: 'orders' },
    { href: 'manual-payments.html', label: 'Payment reviews', icon: 'wallet', badge: 'review-badge' },
    { href: 'passes.html', label: 'Passes', icon: 'passes' },
    { href: 'products.html', label: 'Products', icon: 'products' },
    { href: 'plans.html', label: 'Bundles', icon: 'passes' },
    { href: 'users.html', label: 'Accounts', icon: 'users' },
    { href: 'settings.html', label: 'Settings', icon: 'settings' },
  ];

  function buildShell(activeHref, title, subtitle) {
    const side = Dx.h('aside', { class: 'admin-side' },
      Dx.h('a', { class: 'admin-brand', href: '../index.html' },
        Dx.h('img', { src: '../assets/img/dexxoxxbd.jpeg', alt: 'DexxoxxBD logo' }),
        'DexxoxxBD admin'
      ),
      Dx.h('nav', { class: 'admin-nav', 'aria-label': 'Admin' }, NAV.map(function (item) {
        const link = Dx.h('a', { href: item.href, dataset: {} }, icon(item.icon), Dx.h('span', { text: item.label }));
        if (item.href === activeHref) link.setAttribute('aria-current', 'page');
        if (item.badge) link.appendChild(Dx.h('span', { class: 'nav-badge hide', id: item.badge, text: '0' }));
        return link;
      })),
      Dx.h('div', { class: 'admin-side-foot stack-tight' },
        Dx.h('span', { class: 'name', id: 'admin-name', text: '…' }),
        Dx.h('button', { type: 'button', id: 'admin-logout' }, 'Sign out'),
        Dx.h('a', { href: '../index.html', text: 'View storefront' })
      )
    );

    const main = Dx.h('main', { class: 'admin-main' },
      Dx.h('div', { class: 'admin-topbar' },
        Dx.h('div', null,
          Dx.h('h1', { text: title }),
          subtitle ? Dx.h('p', { text: subtitle }) : null
        ),
        Dx.h('div', { class: 'cluster', id: 'topbar-actions' })
      ),
      Dx.h('div', { id: 'admin-outlet', class: 'stack' })
    );

    const mobile = Dx.h('div', { class: 'admin-mobile-bar' },
      Dx.h('a', { href: '../index.html', text: 'Storefront' }),
      Dx.h('strong', { text: title }),
      Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', id: 'admin-logout-mobile' }, 'Sign out')
    );

    document.body.prepend(main);
    document.body.prepend(side);
    document.body.prepend(mobile);
    document.body.classList.add('admin-shell');

    const page = document.title;
    document.title = title + ' — DexxoxxBD admin';
    return page;
  }

  async function start(title, subtitle, activeHref) {
    const user = await Dx.requireAdmin('../login.html?next=' + encodeURIComponent('admin/dashboard.html'));
    buildShell(activeHref, title, subtitle);
    document.getElementById('admin-name').textContent = user.name + ' · ' + user.role;

    [document.getElementById('admin-logout'), document.getElementById('admin-logout-mobile')].forEach(function (button) {
      if (!button) return;
      button.addEventListener('click', async function () {
        try { await Dx.endpoints.auth.logout(); } catch (error) { /* already signed out */ }
        location.href = '../login.html';
      });
    });

    refreshReviewBadge();
    return user;
  }

  async function refreshReviewBadge() {
    const badge = document.getElementById('review-badge');
    if (!badge) return;
    try {
      const data = await Dx.endpoints.admin.manualPayments('?status=pending&per_page=1');
      const count = Number(data.meta.total || 0);
      badge.textContent = String(count);
      badge.classList.toggle('hide', count === 0);
    } catch (error) { /* badge is decorative */ }
  }

  function stat(label, value, note, accent) {
    return Dx.h('div', { class: 'stat card' + (accent ? ' stat-accent' : '') },
      Dx.h('span', { class: 'label', text: label }),
      Dx.h('span', { class: 'value', text: value }),
      note ? Dx.h('span', { class: 'delta', text: note }) : null
    );
  }

  function sparkline(series, valueKey) {
    const values = series.map(function (row) { return Number(row[valueKey] || 0); });
    const max = Math.max.apply(null, values.concat([1]));

    return Dx.h('div', { class: 'spark', role: 'img', 'aria-label': 'Revenue for the last ' + series.length + ' days' },
      series.map(function (row) {
        const bar = Dx.h('i', null);
        bar.style.setProperty('--h', Math.max(4, Math.round((Number(row[valueKey] || 0) / max) * 100)) + '%');
        bar.title = row.day + ' · ' + Dx.money(row[valueKey]);
        return bar;
      })
    );
  }

  const statusStamp = {
    paid: 'stamp-teal', active: 'stamp-teal', approved: 'stamp-teal', valid: 'stamp-teal',
    pending: 'stamp-gold', initiated: 'stamp-gold',
    failed: 'stamp-danger', expired: 'stamp-danger', rejected: 'stamp-danger', revoked: 'stamp-danger',
    cancelled: 'stamp-faint', inactive: 'stamp-faint', suspended: 'stamp-faint',
  };

  function stamp(status) {
    return Dx.h('span', { class: 'stamp ' + (statusStamp[String(status)] || 'stamp-faint'), text: String(status) });
  }

  function pager(container, meta, page, onGoto) {
    if (meta.total === 0) { Dx.clear(container); return; }
    const last = Math.max(1, Math.ceil(meta.total / meta.per_page));

    Dx.mount(container, [
      Dx.h('span', { text: 'Page ' + page + ' of ' + last + ' · ' + meta.total + ' records' }),
      Dx.h('div', { class: 'pager' },
        Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', disabled: page <= 1, onclick: function () { onGoto(page - 1); } }, 'Previous'),
        Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', disabled: page >= last, onclick: function () { onGoto(page + 1); } }, 'Next')
      )
    ]);
  }

  function confirmAction(message, onYes, button, busyLabel) {
    if (!window.confirm(message)) return;
    Dx.setBusy(button, true, busyLabel || 'Working…');
    onYes().catch(function (error) {
      Dx.setBusy(button, false);
      Dx.toast(error.message, 'error');
    });
  }

  window.Admin = {
    icon: icon,
    start: start,
    stat: stat,
    sparkline: sparkline,
    stamp: stamp,
    pager: pager,
    refreshReviewBadge: refreshReviewBadge,
    confirmAction: confirmAction,
    actions: function (node) {
      const slot = document.getElementById('topbar-actions');
      if (slot) Dx.mount(slot, node);
    },
    outlet: function (content) { return Dx.mount('admin-outlet', content); },
  };
})(window, document);
