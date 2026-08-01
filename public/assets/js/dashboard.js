function passCard(p) {
  const isActive = p.status === 'active';
  return `
  <div class="pass-card p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-xs uppercase tracking-widest text-inksoft font-mono">${p.item_type}</p>
        <p class="font-display font-semibold mt-1">${p.item_name}</p>
      </div>
      <span class="stamp ${isActive ? 'text-teal' : 'text-inksoft'}" style="color:${isActive ? '#1F9C8B' : '#4A5068'}">${p.status}</span>
    </div>
    <div class="pass-perforation my-4"></div>
    <div class="flex justify-between text-sm">
      <span class="text-inksoft">Valid</span>
      <span class="font-medium">${fmtDate(p.starts_at)} → ${fmtDate(p.ends_at)}</span>
    </div>
    <p class="pass-code text-xs text-inksoft mt-4">${p.pass_code}</p>
  </div>`;
}

function orderRow(o) {
  const statusColor = { paid: '#1F9C8B', pending: '#7A4E0E', failed: '#D64545', cancelled: '#4A5068' }[o.status] || '#4A5068';
  return `
  <div class="pass-card p-4 flex items-center justify-between gap-4">
    <div>
      <p class="font-mono text-sm font-semibold">${o.order_number}</p>
      <p class="text-xs text-inksoft">${fmtDate(o.created_at)}</p>
    </div>
    <span class="stamp" style="color:${statusColor}">${o.status}</span>
    <p class="font-display font-semibold">${money(o.total_amount)}</p>
  </div>`;
}

(async function loadDashboard() {
  if (new URLSearchParams(location.search).get('payment') === 'success') {
    document.getElementById('payment-success-banner').classList.remove('hidden');
  }

  try {
    await Api.me();
  } catch {
    toast('Please sign in to view your dashboard.', 'error');
    setTimeout(() => location.href = 'login.html', 800);
    return;
  }

  try {
    const { passes } = await Api.passes();
    document.getElementById('passes-grid').innerHTML = passes.length
      ? passes.map(passCard).join('')
      : `<p class="text-inksoft">No passes yet — <a href="products.html" class="text-navy font-semibold hover:underline">start shopping</a>.</p>`;
  } catch (e) {
    document.getElementById('passes-grid').innerHTML = `<p class="text-danger">${e.message}</p>`;
  }

  try {
    const { orders } = await Api.orders.list();
    document.getElementById('orders-list').innerHTML = orders.length
      ? orders.map(orderRow).join('')
      : `<p class="text-inksoft">No orders yet.</p>`;
  } catch (e) {
    document.getElementById('orders-list').innerHTML = `<p class="text-danger">${e.message}</p>`;
  }
})();
