/* Admin overview: real numbers from /admin_stats.php, no decorative figures. */
(async function initAdminDashboard() {
  await Admin.start('Overview', 'Everything the shop has actually recorded.', 'dashboard.html');

  Admin.actions([
    Dx.h('button', { class: 'btn btn-quiet btn-sm', type: 'button', id: 'refresh-stats' }, 'Refresh'),
    Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', id: 'run-sweep' }, 'Run housekeeping'),
  ]);

  document.getElementById('run-sweep').addEventListener('click', function () {
    const button = this;
    Admin.confirmAction(
      'Expire unpaid orders, mark overdue passes expired and clear stale rate-limit rows?',
      function () {
        return Dx.endpoints.admin.sweep().then(function (result) {
          Dx.toast('Housekeeping done: ' + result.orders_expired + ' orders expired, ' + result.passes_lapsed + ' passes lapsed.', 'success');
          return load();
        });
      },
      button,
      'Running…'
    );
  });

  document.getElementById('refresh-stats').addEventListener('click', load);

  function statRow(stats) {
    return [
      Admin.stat('Revenue collected', Dx.money(stats.total_revenue), stats.paid_orders + ' paid orders'),
      Admin.stat('Average order', Dx.money(stats.average_order_value), stats.total_orders + ' orders all-time', true),
      Admin.stat('Awaiting payment', String(stats.pending_orders), stats.manual_pending + ' of them need a manual review'),
      Admin.stat('Active passes', String(stats.active_passes), stats.expired_passes + ' expired'),
      Admin.stat('Customers', String(stats.total_customers), stats.suspended_users + ' suspended · ' + stats.total_admins + ' admins'),
      Admin.stat('Catalogue gaps', String(stats.out_of_stock), stats.active_products + ' live, ' + stats.inactive_products + ' hidden', true),
    ];
  }

  function topProducts(rows) {
    if (!rows.length) return Dx.h('p', { class: 'muted', text: 'No paid orders yet, so nothing to rank.' });

    const max = Math.max.apply(null, rows.map(function (row) { return Number(row.units); }));

    return Dx.h('div', { class: 'stack-tight' }, rows.map(function (row) {
      const bar = Dx.h('div', { class: 'progress-bar' });
      bar.style.setProperty('--w', Math.max(6, Math.round((Number(row.units) / max) * 100)) + '%');

      return Dx.h('div', { class: 'stack-tight' },
        Dx.h('div', { class: 'summary-line' },
          Dx.h('span', { text: row.item_name }),
          Dx.h('span', { class: 'mono', text: row.units + ' units · ' + Dx.money(row.revenue) })
        ),
        Dx.h('div', { class: 'progress' }, bar)
      );
    }));
  }

  function recentOrders(rows) {
    if (!rows.length) return Dx.h('p', { class: 'muted', text: 'No orders placed yet.' });

    return Dx.h('div', { class: 'table-wrap' }, Dx.h('table', { class: 'table' },
      Dx.h('thead', null, Dx.h('tr', null,
        ['Order', 'Customer', 'Total', 'Status', 'Placed', ''].map(function (label) {
          return Dx.h('th', { scope: 'col', text: label });
        })
      )),
      Dx.h('tbody', null, rows.map(function (row) {
        return Dx.h('tr', null,
          Dx.h('td', { class: 'mono', text: row.order_number }),
          Dx.h('td', null, Dx.h('div', { class: 'cell-title', text: row.user_name }), Dx.h('span', { class: 'sub', text: row.user_email })),
          Dx.h('td', { class: 'num', text: Dx.money(row.total_amount) }),
          Dx.h('td', null, Admin.stamp(row.status)),
          Dx.h('td', { text: Dx.formatDateTime(row.created_at) }),
          Dx.h('td', null, Dx.h('a', { class: 'btn btn-ghost btn-sm', href: 'orders.html?focus=' + row.id }, 'Open'))
        );
      }))
    ));
  }

  async function load() {
    Admin.outlet(Dx.h('div', { class: 'panel' }, Dx.h('div', { class: 'skeleton skeleton-cell' })));

    try {
      const stats = await Dx.endpoints.admin.stats();

      Admin.outlet([
        Dx.h('div', { class: 'stat-grid' }, statRow(stats)),

        Dx.h('div', { class: 'grid-split' },
          Dx.h('div', { class: 'panel stack' },
            Dx.h('div', { class: 'panel-head' },
              Dx.h('h2', { text: 'Revenue, last 14 days' }),
              Dx.h('span', { class: 'stamp stamp-navy', text: 'paid orders only' })
            ),
            Admin.sparkline(stats.revenue_series, 'revenue'),
            Dx.h('div', { class: 'cluster-between hint' },
              Dx.h('span', { text: stats.revenue_series.length ? stats.revenue_series[0].day : '—' }),
              Dx.h('span', { text: stats.revenue_series.length ? stats.revenue_series[stats.revenue_series.length - 1].day : '—' })
            ),
            Dx.h('div', { class: 'divider' }),
            Dx.h('h3', { text: 'Best sellers' }),
            topProducts(stats.top_products)
          ),

          Dx.h('div', { class: 'panel stack' },
            Dx.h('div', { class: 'panel-head' },
              Dx.h('h2', { text: 'Latest orders' }),
              Dx.h('a', { class: 'btn btn-quiet btn-sm', href: 'orders.html' }, 'All orders')
            ),
            recentOrders(stats.recent_orders)
          )
        ),

        stats.manual_pending > 0
          ? Dx.h('div', { class: 'banner banner-warn' },
            Dx.h('strong', { text: stats.manual_pending + ' manual payment' + (stats.manual_pending === 1 ? '' : 's') + ' waiting for review' }),
            Dx.h('span', { class: 'spacer' }),
            Dx.h('a', { class: 'btn btn-gold btn-sm', href: 'manual-payments.html' }, 'Open the queue')
          )
          : null,

        stats.out_of_stock > 0
          ? Dx.h('div', { class: 'banner banner-danger' },
            Dx.h('strong', { text: stats.out_of_stock + ' live product' + (stats.out_of_stock === 1 ? ' is' : 's are') + ' out of stock' }),
            Dx.h('span', { class: 'spacer' }),
            Dx.h('a', { class: 'btn btn-quiet btn-sm', href: 'products.html?stock=empty' }, 'Restock')
          )
          : null
      ]);

      Admin.refreshReviewBadge();
    } catch (error) {
      Admin.outlet(Dx.h('div', { class: 'banner banner-danger', text: error.message }));
    }
  }

  load();
})();
