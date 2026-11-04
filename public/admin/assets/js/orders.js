/* Admin orders: filter, page, settle or close an order. Passes are only ever issued
   by the server, so the button here just reports what the API decided. */
(async function initAdminOrders() {
  await Admin.start('Orders', 'Settle, close and re-open orders.', 'orders.html');

  const STATUSES = ['pending', 'paid', 'failed', 'cancelled', 'expired'];
  let state = { status: Dx.queryParam('status') || '', q: '', page: 1 };

  Admin.outlet(Dx.h('div', { class: 'panel stack' },
    Dx.h('div', { class: 'filters' },
      Dx.h('input', { class: 'input', id: 'order-search', type: 'search', placeholder: 'Order number, name or email…', autocomplete: 'off' }),
      Dx.h('select', { class: 'select', id: 'order-status' },
        Dx.h('option', { value: '', text: 'All statuses' }),
        STATUSES.map(function (status) { return Dx.h('option', { value: status, text: status }); })
      ),
      Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', id: 'order-clear' }, 'Clear filters')
    ),
    Dx.h('div', { class: 'table-wrap' }, Dx.h('table', { class: 'table' },
      Dx.h('thead', null, Dx.h('tr', null,
        ['Order', 'Customer', 'Items', 'Total', 'Status', 'Channel', 'Placed', ''].map(function (label) {
          return Dx.h('th', { scope: 'col', text: label });
        })
      )),
      Dx.h('tbody', { id: 'orders-body' })
    )),
    Dx.h('div', { class: 'pagination', id: 'orders-pagination' })
  ));

  const statusSelect = document.getElementById('order-status');
  statusSelect.value = state.status;

  const searchInput = document.getElementById('order-search');
  let debounce = null;

  searchInput.addEventListener('input', function () {
    clearTimeout(debounce);
    debounce = setTimeout(function () { state.q = searchInput.value.trim(); state.page = 1; load(); }, 320);
  });

  statusSelect.addEventListener('change', function () { state.status = statusSelect.value; state.page = 1; load(); });

  document.getElementById('order-clear').addEventListener('click', function () {
    state = { status: '', q: '', page: 1 };
    searchInput.value = '';
    statusSelect.value = '';
    load();
  });

  async function load() {
    Dx.skeletonRows('orders-body', 8, 5);

    try {
      const data = await Dx.endpoints.admin.orders('?page=' + state.page + '&status=' + encodeURIComponent(state.status) + '&q=' + encodeURIComponent(state.q));
      const body = document.getElementById('orders-body');
      Dx.clear(body);

      if (!data.orders.length) {
        body.appendChild(Dx.h('tr', null, Dx.h('td', { colspan: '8' },
          Dx.h('div', { class: 'empty', text: 'No order matches those filters.' }))));
        Dx.clear('orders-pagination');
        return;
      }

      data.orders.forEach(function (row) {
        body.appendChild(Dx.h('tr', null,
          Dx.h('td', null, Dx.h('div', { class: 'cell-title mono', text: row.order_number })),
          Dx.h('td', null, Dx.h('div', { class: 'cell-title', text: row.user_name }), Dx.h('span', { class: 'sub', text: row.user_email })),
          Dx.h('td', { class: 'num', text: String(row.item_count ?? '—') }),
          Dx.h('td', { class: 'num', text: Dx.money(row.total_amount) }),
          Dx.h('td', null, Admin.stamp(row.status)),
          Dx.h('td', { class: 'mono', text: row.payment_method }),
          Dx.h('td', { text: Dx.formatDateTime(row.created_at) }),
          Dx.h('td', null, Dx.h('div', { class: 'actions' },
            Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', onclick: function () { openOrder(row.id); } }, 'Open')
          ))
        ));
      });

      Admin.pager('orders-pagination', data.meta, state.page, function (next) { state.page = next; load(); });
    } catch (error) {
      Dx.mount('orders-body', Dx.h('tr', null, Dx.h('td', { colspan: '8' }, Dx.h('div', { class: 'banner banner-danger', text: error.message }))));
    }
  }

  function setStatus(order, status, button) {
    const warn = status === 'paid'
      ? 'Mark order ' + order.order_number + ' as paid? Pass codes will be issued to ' + order.user_name + '.'
      : 'Mark order ' + order.order_number + ' as ' + status + '? Reserved stock goes back to the catalogue' + (order.status === 'paid' ? ' and already issued passes are NOT revoked.' : '.');

    Admin.confirmAction(warn, function () {
      return Dx.endpoints.admin.setOrderStatus(order.id, status).then(function (result) {
        Dx.toast(result.message || 'Order is now ' + status + '.', 'success');
        load();
        Admin.refreshReviewBadge();
      });
    }, button, 'Saving…');
  }

  function actionRow(order) {
    const buttons = [];
    const wanted = STATUSES.filter(function (status) { return status !== order.status && status !== 'pending'; });

    wanted.forEach(function (status) {
      const button = Dx.h('button', {
        class: 'btn btn-sm ' + (status === 'paid' ? 'btn-gold' : status === 'failed' ? 'btn-danger' : 'btn-quiet'),
        type: 'button', text: 'Mark ' + status,
      });
      button.addEventListener('click', function () { setStatus(order, status, button); });
      buttons.push(button);
    });

    // Re-open is refused by the API on purpose: a settled order must stay settled.
    if (order.status !== 'pending') {
      buttons.push(Dx.h('span', { class: 'hint', text: 'An order cannot be pushed back to pending.' }));
    }

    return Dx.h('div', { class: 'review-actions' }, buttons);
  }

  async function openOrder(id) {
    const modal = Dx.h('div', { class: 'modal hide', id: 'order-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Order details' },
      Dx.h('div', { class: 'modal-box modal-box-wide' },
        Dx.h('div', { class: 'modal-head' },
          Dx.h('h2', { id: 'order-modal-title' }, 'Order'),
          Dx.h('button', { class: 'icon-btn', type: 'button', 'data-close-modal': 'order-modal', 'aria-label': 'Close' },
            Dx.h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'aria-hidden': 'true' },
              Dx.h('path', { d: 'M6 6l12 12M18 6 6 18' })))
        ),
        Dx.h('div', { class: 'stack', id: 'order-modal-body' }, Dx.h('div', { class: 'skeleton skeleton-cell' }))
      )
    );

    document.body.appendChild(modal);
    Dx.openModal('order-modal');

    try {
      const order = (await Dx.endpoints.admin.order(id)).order;
      Dx.mount('order-modal-title', order.order_number);

      const blocks = [
        Dx.h('div', { class: 'cluster' }, Admin.stamp(order.status), Dx.h('span', { class: 'faint', text: 'placed ' + Dx.formatDateTime(order.created_at) })),
        Dx.h('dl', { class: 'detail-grid' },
          cell('Customer', order.user_name),
          cell('Email', order.user_email),
          cell('Phone', order.customer_phone || '—'),
          cell('Email on order', order.customer_email),
          cell('Total', Dx.money(order.total_amount)),
          cell('Subtotal', Dx.money(order.subtotal)),
          cell('Channel', order.payment_method),
          cell('Paid at', order.paid_at ? Dx.formatDateTime(order.paid_at) : '—'),
          cell('Hold expires', order.expires_at ? Dx.formatDateTime(order.expires_at) : '—'),
          cell('Buyer IP', order.ip_address || '—')
        ),
        Dx.h('h3', { text: 'Items' }),
        Dx.h('div', { class: 'stack-tight' }, order.items.map(function (item) {
          return Dx.h('div', { class: 'summary-line' },
            Dx.h('span', { text: item.item_name + ' × ' + item.quantity }),
            Dx.h('span', { class: 'mono', text: Dx.money(item.line_total) + ' · ' + item.duration_days + 'd' + (item.stock_released ? ' · stock returned' : '') })
          );
        })),
      ];

      if (order.passes.length) {
        blocks.push(Dx.h('h3', { text: 'Passes issued' }), Dx.h('div', { class: 'stack-tight' }, order.passes.map(function (pass) {
          return Dx.h('div', { class: 'pass-code-row' },
            Dx.h('code', { class: 'pass-code', text: pass.pass_code }),
            Admin.stamp(pass.status),
            Dx.h('span', { class: 'faint', text: pass.item_name + ' until ' + Dx.formatDate(pass.ends_at) })
          );
        })));
      }

      if (order.manual_payment) {
        const manual = order.manual_payment;
        blocks.push(Dx.h('div', { class: 'banner ' + (manual.status === 'approved' ? 'banner-teal' : manual.status === 'rejected' ? 'banner-danger' : 'banner-warn') },
          Dx.h('div', { class: 'stack-tight' },
            Dx.h('strong', { text: 'Manual payment ' + manual.status }),
            Dx.h('span', { class: 'mono', text: manual.payment_method + ' · ' + manual.transaction_id + ' · ' + Dx.money(manual.amount) }),
            manual.sender_number ? Dx.h('span', { class: 'faint', text: 'from ' + manual.sender_number }) : null,
            manual.admin_note ? Dx.h('span', { class: 'faint', text: 'Note: ' + manual.admin_note }) : null
          )
        ));
      }

      if (order.payments.length) {
        blocks.push(Dx.h('h3', { text: 'Gateway attempts' }), Dx.h('div', { class: 'stack-tight' }, order.payments.map(function (payment) {
          return Dx.h('div', { class: 'summary-line' },
            Dx.h('span', { class: 'mono', text: payment.tran_id }),
            Dx.h('span', null, Admin.stamp(payment.status), payment.failure_reason ? Dx.h('span', { class: 'faint', text: payment.failure_reason }) : null)
          );
        })));
      }

      blocks.push(Dx.h('div', { class: 'divider' }), Dx.h('h3', { text: 'Settle by hand' }), actionRow(order));

      Dx.mount('order-modal-body', blocks);
    } catch (error) {
      Dx.mount('order-modal-body', Dx.h('div', { class: 'banner banner-danger', text: error.message }));
    }
  }

  function cell(label, value) {
    const node = Dx.h('div', { class: 'detail' });
    node.append(Dx.h('dt', { text: label }), Dx.h('dd', { text: String(value) }));
    return node;
  }

  const focus = Number(Dx.queryParam('focus') || 0);
  await load();
  if (focus > 0) openOrder(focus);
})();
