function statCard(label, value, accent, link) {
  const inner = `
    <p class="text-xs uppercase tracking-widest text-inksoft font-mono">${label}</p>
    <p class="font-display text-3xl font-bold mt-2" style="color:${accent || '#12172B'}">${value}</p>
  `;
  if (link) {
    return `<a href="${link}" class="pass-card p-5 block hover:shadow-md transition">${inner}</a>`;
  }
  return `<div class="pass-card p-5">${inner}</div>`;
}

function statusStamp(status) {
  const color = { paid: '#1F9C8B', pending: '#7A4E0E', failed: '#D64545', cancelled: '#4A5068' }[status] || '#4A5068';
  return `<span class="stamp" style="color:${color}">${status}</span>`;
}

(async function loadAdminDashboard() {
  try { await requireAdminAuth(); } catch { return; }

  document.getElementById('admin-logout-btn').addEventListener('click', async () => {
    await AdminApi.logout();
    location.href = 'index.html';
  });

  try {
    const s = await AdminApi.stats();

    // Show pending badge on nav if there are awaiting manual payments
    if (s.pending_manual_payments > 0) {
      const badge = document.getElementById('nav-pending-badge');
      if (badge) {
        badge.textContent = s.pending_manual_payments;
        badge.classList.remove('hidden');
      }
    }

    document.getElementById('stats-grid').innerHTML = [
      statCard('Total revenue',            money(s.total_revenue),              '#1F9C8B'),
      statCard('Total orders',             s.total_orders,                      null,     'orders.html'),
      statCard('Pending orders',           s.pending_orders,                    '#7A4E0E','orders.html?status=pending'),
      statCard('Active passes',            s.active_passes,                     '#1F9C8B'),
      statCard('Customers',                s.total_customers,                   null,     'users.html'),
      statCard('Products',                 s.total_products,                    null,     'products.html'),
      statCard('Plans',                    s.total_plans,                       null,     'plans.html'),
      statCard(
        'Pending Manual Payments',
        s.pending_manual_payments,
        s.pending_manual_payments > 0 ? '#D64545' : '#12172B',
        'manual-payments.html'
      ),
    ].join('');

    document.getElementById('recent-orders').innerHTML = s.recent_orders.length
      ? s.recent_orders.map(o => `
        <div class="flex items-center justify-between text-sm border-b border-line/60 pb-3 last:border-0">
          <div>
            <p class="font-mono font-semibold">${o.order_number}</p>
            <p class="text-inksoft text-xs">${o.user_name} · ${fmtDate(o.created_at)}</p>
          </div>
          ${statusStamp(o.status)}
          <p class="font-display font-semibold">${money(o.total_amount)}</p>
        </div>`).join('')
      : `<p class="text-inksoft text-sm">No orders yet.</p>`;
  } catch (e) {
    adminToast(e.message, 'error');
  }
})();
