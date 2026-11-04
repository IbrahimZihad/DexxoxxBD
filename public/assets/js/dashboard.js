/* Customer dashboard: pass wallet, order history, profile. */
(function initDashboard() {
  let user = null;
  let ordersMeta = null;
  let settings = {};

  /* ---------------- tabs ---------------- */
  function switchTab(name) {
    document.querySelectorAll('#dash-tabs .tab').forEach(function (tab) {
      const active = tab.dataset.tab === name;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    ['passes', 'orders', 'profile'].forEach(function (key) {
      document.getElementById('tab-' + key).classList.toggle('hide', key !== name);
    });

    if (name === 'orders' && !ordersMeta) loadOrders(1);
  }

  /* ---------------- passes ---------------- */
  function passStub(pass) {
    const live = pass.status === 'active';
    const expiring = live && pass.days_left !== null && pass.days_left <= 7;
    const progress = Dx.h('div', { class: 'progress-bar' });
    const ratio = live && pass.starts_at && pass.ends_at
      ? Math.min(100, Math.max(2, Math.round((pass.days_left / Math.max(1, Dx.daysBetween(pass.starts_at, pass.ends_at))) * 100)))
      : 0;
    progress.style.setProperty('--w', ratio + '%');

    const code = Dx.h('code', { class: 'pass-code', text: pass.pass_code });
    const copyButton = Dx.h('button', { class: 'copy-btn', type: 'button' }, 'Copy code');
    copyButton.addEventListener('click', function () { copy(pass.pass_code, code, copyButton); });

    const stamp = live
      ? (expiring ? Dx.h('span', { class: 'stamp stamp-gold stamp-pulse' }, expiring + ' days left') : Dx.h('span', { class: 'stamp stamp-teal stamp-pulse' }, 'Active'))
      : Dx.h('span', { class: 'stamp stamp-' + (pass.status === 'revoked' ? 'danger' : 'faint') }, pass.status);

    const renew = Dx.h('a', { class: 'btn btn-ghost btn-sm', href: pass.item_type === 'plan' ? 'plans.html' : 'products.html?q=' + encodeURIComponent(pass.item_name) }, 'Renew');

    return Dx.h('article', { class: 'pass-card reveal' + (live ? '' : ' is-lapsed') },
      Dx.h('div', { class: 'pass-head' },
        Dx.h('div', { class: 'stack-tight' },
          Dx.h('h3', { class: 'pass-title', text: pass.item_name }),
          Dx.h('span', { class: 'tile-meta mono', text: (pass.item_type === 'plan' ? 'Bundle pass' : 'Single pass') + ' · ' + pass.id })
        ),
        stamp
      ),
      Dx.h('div', { class: 'pass-body' },
        Dx.h('div', { class: 'pass-code-row' }, code, copyButton),
        Dx.h('div', { class: 'barcode', role: 'img', 'aria-label': 'Decorative barcode for pass ' + pass.pass_code }),
        live ? Dx.h('div', { class: 'stack-tight' }, Dx.h('div', { class: 'progress' }, progress),
          Dx.h('span', { class: 'hint', text: pass.days_left + ' of ' + Math.max(1, Dx.daysBetween(pass.starts_at, pass.ends_at)) + ' days remaining' })) : null,
        Dx.h('div', { class: 'pass-meta' },
          Dx.h('span', null, 'From ', Dx.h('strong', { text: Dx.formatDate(pass.starts_at) })),
          Dx.h('span', null, 'Until ', Dx.h('strong', { text: Dx.formatDate(pass.ends_at) }))
        ),
        Dx.h('div', { class: 'cluster' }, renew,
          Dx.h('span', { class: 'spacer' }),
          Dx.h('span', { class: 'faint', text: 'Purchased ' + Dx.formatDate(pass.created_at) })
        )
      ),
      Dx.h('div', { class: 'pass-perforation' })
    );
  }

  function copy(text, node, button) {
    const done = function () {
      button.textContent = 'Copied';
      node.classList.add('is-dirty');
      setTimeout(function () { node.classList.remove('is-dirty'); }, 400);
      setTimeout(function () { button.textContent = 'Copy code'; }, 1800);
    };

    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(done).catch(function () { Dx.toast('Copy blocked — select the code manually.'); });
      return;
    }

    const range = document.createRange();
    range.selectNodeContents(node);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    Dx.toast('Code selected — copy it now.');
  }

  async function loadPasses() {
    Dx.skeletonCards('pass-grid', 3, 'skeleton-tile');

    try {
      const data = await Dx.endpoints.passes();
      const grid = document.getElementById('pass-grid');
      Dx.clear(grid);

      Dx.mount('dash-summary', [
        Dx.h('span', { class: 'stamp stamp-teal', text: data.summary.active + ' active' }),
        data.summary.expiring_soon
          ? Dx.h('span', { class: 'stamp stamp-gold', text: data.summary.expiring_soon + ' expiring soon' })
          : null
      ]);

      if (!data.passes.length) {
        grid.appendChild(Dx.h('div', { class: 'empty' },
          Dx.h('h3', { text: 'No passes yet' }),
          Dx.h('p', { class: 'muted', text: 'Buy a bundle and your first pass code appears here within minutes.' }),
          Dx.h('div', { class: 'cluster' },
            Dx.h('a', { class: 'btn btn-primary', href: 'plans.html' }, 'Browse passes'),
            Dx.h('a', { class: 'btn btn-ghost', href: 'products.html' }, 'Single services')
          )
        ));
        return;
      }

      data.passes.forEach(function (pass) { grid.appendChild(passStub(pass)); });
      Dx.reveal(grid);
    } catch (error) {
      Dx.mount('pass-grid', Dx.h('div', { class: 'banner banner-danger', text: error.message }));
    }
  }

  /* ---------------- orders ---------------- */
  const statusStamp = {
    paid: 'stamp-teal',
    pending: 'stamp-gold',
    failed: 'stamp-danger',
    cancelled: 'stamp-faint',
    expired: 'stamp-danger',
  };

  async function loadOrders(page) {
    Dx.skeletonRows('orders-body', 7, 4);

    try {
      const data = await Dx.endpoints.orders.list('?page=' + page);
      ordersMeta = data.meta;
      const body = document.getElementById('orders-body');
      Dx.clear(body);

      if (!data.orders.length) {
        body.appendChild(Dx.h('tr', null, Dx.h('td', { colspan: '7' },
          Dx.h('div', { class: 'empty', text: 'No orders placed from this account yet.' }))));
        Dx.clear('orders-pagination');
        return;
      }

      data.orders.forEach(function (row) {
        const actions = [];

        if (row.status === 'pending') {
          actions.push(Dx.h('a', { class: 'btn btn-gold btn-sm', href: 'checkout.html?payment=retry&order=' + row.id }, 'Pay now'));
        }

        actions.push(Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', onclick: function () { openOrder(row.id); } }, 'Details'));

        body.appendChild(Dx.h('tr', null,
          Dx.h('td', null, Dx.h('div', { class: 'cell-title mono', text: row.order_number }),
            Dx.h('span', { class: 'tile-meta', text: row.item_count + ' item' + (row.item_count === 1 ? '' : 's') })),
          Dx.h('td', { text: Dx.formatDateTime(row.created_at) }),
          Dx.h('td', { class: 'num', text: Dx.money(row.total_amount) }),
          Dx.h('td', null, Dx.h('span', { class: 'stamp ' + (statusStamp[row.status] || 'stamp-faint'), text: row.status })),
          Dx.h('td', { text: row.payment_method === 'pending' ? '—' : row.payment_method }),
          Dx.h('td', { text: row.status === 'pending' ? Dx.formatDateTime(row.expires_at) : (row.paid_at ? Dx.formatDate(row.paid_at) : '—') }),
          Dx.h('td', null, Dx.h('div', { class: 'actions' }, actions))
        ));
      });

      const lastPage = Math.max(1, Math.ceil(data.meta.total / data.meta.per_page));
      Dx.mount('orders-pagination', [
        Dx.h('span', { text: data.meta.total + ' order' + (data.meta.total === 1 ? '' : 's') }),
        Dx.h('div', { class: 'pager' },
          Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', disabled: page <= 1, onclick: function () { loadOrders(page - 1); } }, 'Previous'),
          Dx.h('span', { class: 'chip', text: page + ' / ' + lastPage }),
          Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', disabled: page >= lastPage, onclick: function () { loadOrders(page + 1); } }, 'Next')
        )
      ]);
    } catch (error) {
      Dx.mount('orders-body', Dx.h('tr', null, Dx.h('td', { colspan: '7' }, Dx.h('div', { class: 'banner banner-danger', text: error.message }))));
    }
  }

  function kv(label, value) {
    return [Dx.h('dt', { text: label }), Dx.h('dd', { class: 'mono', text: value })];
  }

  async function openOrder(id) {
    const modal = Dx.openModal('order-modal');
    if (!modal) return;
    Dx.mount('order-modal-body', Dx.h('div', { class: 'stack' },
      Dx.h('div', { class: 'skeleton skeleton-cell' }),
      Dx.h('div', { class: 'skeleton skeleton-wide skeleton-cell' })
    ));

    try {
      const order = (await Dx.endpoints.orders.get(id)).order;
      Dx.mount('order-modal-title', 'Order ' + order.order_number);

      const blocks = [Dx.h('dl', { class: 'detail-grid' },
        kv('Status', order.status),
        kv('Total', Dx.money(order.total_amount)),
        kv('Placed', Dx.formatDateTime(order.created_at)),
        kv('Paid at', order.paid_at ? Dx.formatDateTime(order.paid_at) : '—'),
        kv('Channel', order.payment_method === 'pending' ? 'Not chosen yet' : order.payment_method),
        order.status === 'pending' ? kv('Hold expires', Dx.formatDateTime(order.expires_at)) : null
      )];

      const items = Dx.h('div', { class: 'stack-tight' }, order.items.map(function (item) {
        return Dx.h('div', { class: 'summary-line' },
          Dx.h('span', { text: item.item_name + ' × ' + item.quantity }),
          Dx.h('span', { class: 'mono', text: Dx.money(item.line_total) + ' · ' + item.duration_days + 'd' })
        );
      }));
      blocks.push(Dx.h('h3', { text: 'Items' }), items);

      if (order.passes.length) {
        blocks.push(Dx.h('h3', { text: 'Pass codes' }), Dx.h('div', { class: 'stack-tight' }, order.passes.map(function (pass) {
          return Dx.h('div', { class: 'pass-code-row' },
            Dx.h('code', { class: 'pass-code', text: pass.pass_code }),
            Dx.h('span', { class: 'stamp ' + (pass.status === 'active' ? 'stamp-teal' : 'stamp-faint'), text: pass.status }),
            Dx.h('span', { class: 'faint', text: pass.item_name })
          );
        })));
      }

      if (order.manual_payment) {
        const manual = order.manual_payment;
        blocks.push(Dx.h('div', { class: 'banner ' + (manual.status === 'approved' ? 'banner-teal' : manual.status === 'rejected' ? 'banner-danger' : 'banner-warn') },
          Dx.h('div', { class: 'stack-tight' },
            Dx.h('strong', { text: 'Manual payment ' + manual.status }),
            Dx.h('span', { class: 'muted', text: manual.payment_method + ' · ref ' + manual.transaction_id + ' · ' + Dx.formatDateTime(manual.created_at) }),
            manual.admin_note ? Dx.h('span', { class: 'muted', text: 'Note from us: ' + manual.admin_note }) : null
          )
        ));
      }

      if (order.payments && order.payments.length) {
        blocks.push(Dx.h('h3', { text: 'Gateway attempts' }), Dx.h('div', { class: 'stack-tight' }, order.payments.map(function (payment) {
          return Dx.h('div', { class: 'summary-line' },
            Dx.h('span', { class: 'mono', text: payment.tran_id }),
            Dx.h('span', { class: 'stamp ' + (payment.status === 'valid' ? 'stamp-teal' : 'stamp-faint'), text: payment.status + (payment.card_type ? ' · ' + payment.card_type : '') })
          );
        })));
      }

      if (order.status === 'pending') {
        blocks.push(Dx.h('div', { class: 'modal-actions' },
          Dx.h('a', { class: 'btn btn-gold', href: 'checkout.html?payment=retry&order=' + order.id }, 'Complete payment'),
          Dx.h('button', { class: 'btn btn-quiet', type: 'button', 'data-close-modal': 'order-modal' }, 'Close')
        ));
      }

      Dx.mount('order-modal-body', blocks);
    } catch (error) {
      Dx.mount('order-modal-body', Dx.h('div', { class: 'banner banner-danger', text: error.message }));
    }
  }

  /* ---------------- profile ---------------- */
  function renderProfile() {
    Dx.mount('profile-body', [
      Dx.h('h2', { class: 'display' }, 'Your details'),
      Dx.h('dl', { class: 'detail-grid' },
        kv('Name', user.name),
        kv('Email', user.email),
        kv('Phone', user.phone || 'Not saved'),
        kv('Member since', Dx.formatDate(user.created_at)),
        kv('Last sign-in', user.last_login_at ? Dx.formatDateTime(user.last_login_at) : 'This session'),
        kv('Account role', user.role)
      ),
      Dx.h('p', { class: 'hint', text: 'To change your email or password, sign out and contact support — we verify the account first so a stolen session cannot lock you out.' }),
      Dx.h('button', { class: 'btn btn-danger btn-sm', type: 'button', id: 'profile-logout' }, 'Sign out of this device')
    ]);

    document.getElementById('profile-logout').addEventListener('click', async function () {
      Dx.setBusy(this, true, 'Signing out…');
      try { await Dx.endpoints.auth.logout(); } catch (error) { /* already gone */ }
      location.href = 'index.html';
    });

    Dx.mount('profile-help', [
      Dx.h('h2', { class: 'display' }, 'Need a hand?'),
      Dx.h('ul', { class: 'stack-tight' }, [
        settings.support_email ? 'Email ' + settings.support_email : null,
        settings.support_phone ? 'Call ' + settings.support_phone : null,
        settings.support_hours ? 'Open ' + settings.support_hours : null,
      ].filter(Boolean).map(function (line) { return Dx.h('li', { class: 'muted', text: line }); })),
      Dx.h('div', { class: 'divider' }),
      Dx.h('p', { class: 'muted', text: 'Pass codes are never shared with other buyers. If a service asks for your DexxoxxBD details, that is not us.' }),
      Dx.h('a', { class: 'btn btn-primary btn-block', href: 'products.html' }, 'Buy another pass')
    ]);
  }

  /* ---------------- boot ---------------- */
  (async function boot() {
    user = await Dx.requireUser('login.html?next=' + encodeURIComponent('dashboard.html'));
    Dx.renderAuthArea(user);
    Dx.refreshCartBadge();
    renderProfile();
    document.getElementById('greeting').textContent = 'Welcome back, ' + String(user.name).split(' ')[0];
    document.getElementById('footer-year').textContent = String(new Date().getFullYear());

    document.querySelectorAll('#dash-tabs .tab').forEach(function (tab) {
      tab.addEventListener('click', function () { switchTab(tab.dataset.tab); });
    });

    try {
      settings = (await Dx.endpoints.settings.public()).settings;
    } catch (error) { /* help panel falls back to what the account already knows */ }

    await loadPasses();

    const flag = Dx.queryParam('payment');
    if (flag === 'success') Dx.toast('Payment confirmed — your pass is live.', 'success');
    if (flag === 'pending') Dx.toast('We are verifying your payment — the order stays pending until it clears.', 'success');

    const orderParam = Dx.queryParam('order');
    if (orderParam) { switchTab('orders'); openOrder(Number(orderParam)); }
  })();
})();
