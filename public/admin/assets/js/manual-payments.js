const METHOD_LABELS = { bkash: 'bKash', nagad: 'Nagad', rocket: 'Rocket', bank: 'Bank' };

function statusStamp(status) {
  const color = { approved: '#1F9C8B', pending: '#7A4E0E', rejected: '#D64545' }[status] || '#4A5068';
  return `<span class="stamp" style="color:${color}">${status}</span>`;
}

function paymentRow(p) {
  return `
  <tr class="border-b border-line/60 last:border-0">
    <td class="p-4 font-mono">${p.order_number}</td>
    <td class="p-4">${p.user_name}<br><span class="text-xs text-inksoft">${p.user_email}</span></td>
    <td class="p-4 font-semibold">${METHOD_LABELS[p.payment_method] || p.payment_method}</td>
    <td class="p-4 font-mono">${p.sender_number || '—'}</td>
    <td class="p-4 font-mono">${p.transaction_id}</td>
    <td class="p-4 font-mono">${money(p.amount)}</td>
    <td class="p-4">${statusStamp(p.status)}</td>
    <td class="p-4 text-inksoft">${fmtDate(p.created_at)}</td>
    <td class="p-4 text-right">
      ${p.status === 'pending'
        ? `<button class="review-btn text-navy font-semibold hover:underline" data-id="${p.id}" data-order="${p.order_number}" data-amount="${p.amount}" data-method="${p.payment_method}" data-sender="${p.sender_number||''}" data-txid="${p.transaction_id}" data-note="${(p.note||'').replace(/"/g,'&quot;')}">Review</button>`
        : `<span class="text-inksoft text-xs">${p.admin_note ? 'Note: ' + p.admin_note : '—'}</span>`}
    </td>
  </tr>`;
}

async function loadPayments() {
  const tbody = document.getElementById('payments-tbody');
  const status = document.getElementById('status-filter').value;
  try {
    const { payments } = await AdminApi.manualPayments.list(status);
    tbody.innerHTML = payments.length
      ? payments.map(paymentRow).join('')
      : `<tr><td class="p-4 text-inksoft" colspan="9">No payments found.</td></tr>`;
    tbody.querySelectorAll('.review-btn').forEach(b => b.addEventListener('click', () => openReview(b.dataset)));
  } catch (e) {
    tbody.innerHTML = `<tr><td class="p-4 text-danger" colspan="9">${e.message}</td></tr>`;
  }
}

function openReview(data) {
  const modal = document.getElementById('review-modal');
  const body  = document.getElementById('review-modal-body');
  modal.classList.remove('hidden');

  body.innerHTML = `
    <div class="flex justify-between"><span class="text-inksoft">Order</span><span class="font-mono font-semibold">${data.order}</span></div>
    <div class="flex justify-between"><span class="text-inksoft">Amount</span><span class="font-mono font-bold text-lg">${money(data.amount)}</span></div>
    <div class="flex justify-between"><span class="text-inksoft">Method</span><span class="font-semibold">${METHOD_LABELS[data.method] || data.method}</span></div>
    <div class="flex justify-between"><span class="text-inksoft">Sender No.</span><span class="font-mono">${data.sender || '—'}</span></div>
    <div class="flex justify-between"><span class="text-inksoft">Transaction ID</span><span class="font-mono font-semibold">${data.txid}</span></div>
    ${data.note ? `<div class="flex justify-between"><span class="text-inksoft">Customer Note</span><span>${data.note}</span></div>` : ''}
    <div class="pass-perforation my-3"></div>
    <div>
      <label class="text-sm font-medium">Admin Note <span class="text-inksoft">(optional)</span></label>
      <input id="admin-note-input" placeholder="Reason for rejection or confirmation note" class="w-full mt-1 border border-line rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-gold">
    </div>
    <div class="flex gap-3 mt-2">
      <button id="approve-btn" data-id="${data.id}" class="flex-1 bg-teal text-white font-semibold py-2.5 rounded-full hover:opacity-90 transition">✓ Approve &amp; Activate</button>
      <button id="reject-btn"  data-id="${data.id}" class="flex-1 bg-danger text-white font-semibold py-2.5 rounded-full hover:opacity-90 transition">✕ Reject</button>
    </div>
    <p class="text-xs text-inksoft text-center">Approving will immediately mark the order as paid and issue subscription passes to the customer.</p>
  `;

  async function handleAction(action) {
    const adminNote = document.getElementById('admin-note-input').value.trim();
    if (action === 'reject' && !adminNote) {
      adminToast('Please enter a reason for rejection.', 'error');
      return;
    }
    const btn = document.getElementById(action + '-btn');
    btn.disabled = true;
    btn.textContent = 'Processing…';
    try {
      await AdminApi.manualPayments.review(Number(data.id), action, adminNote);
      adminToast('Payment ' + (action === 'approve' ? 'approved and order activated!' : 'rejected.'),
                 action === 'approve' ? 'success' : 'info');
      modal.classList.add('hidden');
      loadPayments();
    } catch (e) {
      adminToast(e.message, 'error');
      btn.disabled = false;
      btn.textContent = action === 'approve' ? '✓ Approve & Activate' : '✕ Reject';
    }
  }

  document.getElementById('approve-btn').addEventListener('click', () => handleAction('approve'));
  document.getElementById('reject-btn').addEventListener('click', () => handleAction('reject'));
}

(async function init() {
  try { await requireAdminAuth(); } catch { return; }
  document.getElementById('admin-logout-btn').addEventListener('click', async () => {
    await AdminApi.logout(); location.href = 'index.html';
  });
  document.getElementById('status-filter').addEventListener('change', loadPayments);
  document.getElementById('close-review-modal').addEventListener('click', () => {
    document.getElementById('review-modal').classList.add('hidden');
  });
  loadPayments();
})();
