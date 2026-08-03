function statusStamp(status) {
  const color = { paid: '#1F9C8B', pending: '#7A4E0E', failed: '#D64545', cancelled: '#4A5068' }[status] || '#4A5068';
  return `<span class="stamp" style="color:${color}">${status}</span>`;
}

function orderRow(o) {
  return `
  <tr class="border-b border-line/60 last:border-0">
    <td class="p-4 font-mono">${o.order_number}</td>
    <td class="p-4">${o.user_name}<br><span class="text-xs text-inksoft">${o.user_email}</span></td>
    <td class="p-4 font-mono">${money(o.total_amount)}</td>
    <td class="p-4 capitalize">${o.payment_method || "sslcommerz"}</td>
    <td class="p-4">${statusStamp(o.status)}</td>
    <td class="p-4 text-inksoft">${fmtDate(o.created_at)}</td>
    <td class="p-4 text-right"><button class="view-btn text-navy font-semibold hover:underline" data-id="${o.id}">View</button></td>
  </tr>`;
}

async function loadOrders() {
  const tbody = document.getElementById('orders-tbody');
  const status = document.getElementById('status-filter').value;
  try {
    const { orders } = await AdminApi.orders.list(status);
    tbody.innerHTML = orders.length ? orders.map(orderRow).join('') : `<tr><td class="p-4 text-inksoft" colspan="6">No orders found.</td></tr>`;
    tbody.querySelectorAll('.view-btn').forEach(b => b.addEventListener('click', () => openOrder(Number(b.dataset.id))));
  } catch (e) {
    tbody.innerHTML = `<tr><td class="p-4 text-danger" colspan="6">${e.message}</td></tr>`;
  }
}

async function openOrder(id) {
  const modal = document.getElementById('order-modal');
  const body = document.getElementById('order-modal-body');
  modal.classList.remove('hidden');
  body.innerHTML = `<p class="text-inksoft">Loading…</p>`;

  try {
    const { order } = await AdminApi.orders.get(id);
    document.getElementById('order-modal-title').textContent = order.order_number;
    body.innerHTML = `
      <div class="flex justify-between"><span class="text-inksoft">Customer</span><span class="font-medium">${order.user_name} (${order.user_email})</span></div>
      <div class="flex justify-between"><span class="text-inksoft">Phone</span><span class="font-medium">${order.customer_phone || '—'}</span></div>
      <div class="flex justify-between"><span class="text-inksoft">Payment Method</span><span class="font-semibold capitalize">${order.payment_method || 'sslcommerz'}</span></div>
      <div class="flex justify-between"><span class="text-inksoft">Placed</span><span class="font-medium">${fmtDate(order.created_at)}</span></div>
      <div class="pass-perforation my-3"></div>
      ${order.items.map(i => `
        <div class="flex justify-between text-sm">
          <span>${i.item_name} × ${i.quantity}</span>
          <span class="font-mono">${money(i.line_total)}</span>
        </div>`).join('')}
      <div class="pass-perforation my-3"></div>
      <div class="flex justify-between font-display font-bold text-lg"><span>Total</span><span>${money(order.total_amount)}</span></div>
      <div class="mt-4">
        <label class="text-sm font-medium">Update status</label>
        <select id="order-status-select" class="w-full mt-1 border border-line rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-gold">
          ${['pending','paid','failed','cancelled'].map(s => `<option value="${s}" ${s === order.status ? 'selected' : ''}>${s}</option>`).join('')}
        </select>
        <button id="save-status-btn" class="w-full mt-3 bg-navy text-white font-semibold py-2.5 rounded-full hover:bg-ink transition">Save status</button>
        <p class="text-xs text-inksoft mt-2">Marking an order "paid" issues subscription passes to the customer immediately.</p>
      </div>
    `;

    document.getElementById('save-status-btn').addEventListener('click', async () => {
      const newStatus = document.getElementById('order-status-select').value;
      try {
        await AdminApi.orders.updateStatus(id, newStatus);
        adminToast('Order status updated.', 'success');
        modal.classList.add('hidden');
        loadOrders();
      } catch (e) { adminToast(e.message, 'error'); }
    });
  } catch (e) {
    body.innerHTML = `<p class="text-danger">${e.message}</p>`;
  }
}

(async function initOrdersPage() {
  try { await requireAdminAuth(); } catch { return; }
  document.getElementById('admin-logout-btn').addEventListener('click', async () => { await AdminApi.logout(); location.href = 'index.html'; });
  document.getElementById('status-filter').addEventListener('change', loadOrders);
  document.getElementById('close-order-modal').addEventListener('click', () => document.getElementById('order-modal').classList.add('hidden'));
  loadOrders();
})();
