/* Every pass ever issued. Revoking hides it from the customer's dashboard from
   the next request; it does not refund the order or touch stock. */
(async function initPasses() {
  await Admin.start('Passes', 'Seats sold, their validity, and what you can still cancel.', 'passes.html');

  const BUCKETS = ['active', 'expiring', 'expired', 'revoked', ''];
  let requested = Dx.queryParam('status');
  let state = { status: BUCKETS.indexOf(requested) === -1 ? 'active' : requested, page: 1 };

  Admin.outlet(Dx.h('div', { class: 'panel stack' },
    Dx.h('div', { class: 'filters', id: 'pass-tabs' }),
    Dx.h('p', { class: 'muted', text: 'Passes whose end date has passed are marked expired the moment this list loads, so what you see is what the customer sees.' }),
    Dx.h('div', { class: 'table-wrap' }, Dx.h('table', { class: 'table' },
      Dx.h('thead', null, Dx.h('tr', null,
        ['Code', 'Product', 'Holder', 'Starts', 'Ends', 'Left', 'Status', ''].map(function (label) {
          return Dx.h('th', { scope: 'col', text: label });
        })
      )),
      Dx.h('tbody', { id: 'pass-body' })
    )),
    Dx.h('div', { class: 'pagination', id: 'pass-pagination' })
  ));

  function tabs() {
    Dx.mount('pass-tabs', [['active', 'Active'], ['expiring', 'Expiring soon'], ['expired', 'Expired'], ['revoked', 'Revoked'], ['', 'All']].map(function (option) {
      const chip = Dx.h('button', { class: 'chip', type: 'button', text: option[1] });
      if (state.status === option[0]) chip.classList.add('is-active');
      chip.addEventListener('click', function () {
        state.status = option[0];
        state.page = 1;
        load();
      });
      return chip;
    }));
  }

  function revoke(row, button) {
    Admin.confirmAction(
      'Revoke ' + row.pass_code + ' for ' + (row.user_email || 'this customer') + '? Access stops on their next request and the order is not refunded.',
      function () {
        return Dx.endpoints.admin.revokePass(row.id).then(function () {
          Dx.toast('Pass revoked.', 'success');
          load();
        });
      },
      button,
      'Revoking…'
    );
  }

  async function load() {
    tabs();
    Dx.skeletonRows('pass-body', 8, 4);

    try {
      const data = await Dx.endpoints.admin.passes('?page=' + state.page + '&status=' + encodeURIComponent(state.status));

      const body = document.getElementById('pass-body');
      Dx.clear(body);

      if (!data.passes.length) {
        body.appendChild(Dx.h('tr', null, Dx.h('td', { colspan: '8' },
          Dx.h('div', { class: 'empty', text: state.status === 'active' ? 'No active passes yet — they appear once an order is paid.' : 'Nothing in this bucket.' }))));
        Dx.clear('pass-pagination');
        return;
      }

      data.passes.forEach(function (row) {
        const expiring = row.status === 'active' && Number(row.days_left) <= 7;

        body.appendChild(Dx.h('tr', null,
          Dx.h('td', null, Dx.h('code', { class: 'pass-code', text: row.pass_code })),
          Dx.h('td', null,
            Dx.h('div', { class: 'cell-title stack-tight' },
              Dx.h('span', { text: row.item_name }),
              Dx.h('span', { class: 'sub', text: row.item_type === 'bundle' ? 'bundle' : 'single seat' })
            )
          ),
          Dx.h('td', null,
            Dx.h('div', { text: row.user_name || '—' }),
            Dx.h('span', { class: 'sub', text: row.user_email || '' })
          ),
          Dx.h('td', { text: Dx.formatDate(row.starts_at) }),
          Dx.h('td', { text: Dx.formatDate(row.ends_at) }),
          Dx.h('td', { class: 'num' }, row.status === 'active'
            ? Dx.h('span', { class: expiring ? 'text-gold' : '', text: row.days_left + 'd' })
            : Dx.h('span', { class: 'faint', text: '—' })),
          Dx.h('td', null, Admin.stamp(row.status)),
          Dx.h('td', null, row.status === 'revoked'
            ? Dx.h('span', { class: 'faint', text: 'done' })
            : Dx.h('button', { class: 'btn btn-quiet btn-sm', type: 'button', text: 'Revoke', onclick: function () { revoke(row, this); } }))
        ));
      });

      Admin.pager('pass-pagination', data.meta, state.page, function (next) { state.page = next; load(); });
    } catch (error) {
      Dx.mount('pass-body', Dx.h('tr', null, Dx.h('td', { colspan: '8' }, Dx.h('div', { class: 'banner banner-danger', text: error.message }))));
    }
  }

  load();
})();
