(async function initCheckout() {
  const banner = document.getElementById('payment-banner');
  const paymentStatus = new URLSearchParams(location.search).get('payment');
  if (paymentStatus === 'fail') {
    banner.className = 'mb-6 rounded-xl p-4 text-sm font-medium bg-danger/10 text-danger';
    banner.textContent = 'Payment failed. Please try again or contact support.';
    banner.classList.remove('hidden');
  } else if (paymentStatus === 'cancel') {
    banner.className = 'mb-6 rounded-xl p-4 text-sm font-medium bg-gold/20 text-goldink';
    banner.textContent = 'Payment was cancelled. Your cart items are still saved.';
    banner.classList.remove('hidden');
  }

  try {
    const { user } = await Api.me();
    document.getElementById('customer_name').value = user.name;
    document.getElementById('customer_email').value = user.email;
    if (user.phone) document.getElementById('customer_phone').value = user.phone;
  } catch {
    toast('Please sign in to check out.', 'error');
    setTimeout(() => location.href = 'login.html', 800);
    return;
  }

  try {
    const { items, total } = await Api.cart.list();
    if (!items.length) {
      document.getElementById('checkout-items').innerHTML = `<p class="text-inksoft">Your cart is empty.</p>`;
      document.getElementById('pay-btn').disabled = true;
      document.getElementById('pay-btn').classList.add('opacity-50', 'pointer-events-none');
      return;
    }
    document.getElementById('checkout-items').innerHTML = items.map(i => `
      <div class="flex justify-between">
        <span class="text-inksoft">${i.name} × ${i.quantity}</span>
        <span class="font-mono">${money(i.line_total)}</span>
      </div>`).join('');
    document.getElementById('checkout-total').textContent = money(total);
  } catch (e) {
    toast(e.message, 'error');
  }

  // ── Form submit ──────────────────────────────────────────────
  document.getElementById('checkout-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('pay-btn');
    btn.disabled = true;
    btn.textContent = 'Processing…';

    try {
      const payload = {
        customer_name:  document.getElementById('customer_name').value.trim(),
        customer_email: document.getElementById('customer_email').value.trim(),
        customer_phone: document.getElementById('customer_phone').value.trim(),
      };

      // Create order first
      const order = await Api.orders.create(payload);

      // Manual payment flow
      const txid   = document.getElementById('manual_txid').value.trim();
      const sender = document.getElementById('manual_sender').value.trim();
      const method = document.getElementById('manual_method').value;
      const note   = document.getElementById('manual_note').value.trim();

      if (!txid) {
        toast('Please enter your Transaction ID.', 'error');
        btn.disabled = false;
        btn.textContent = 'Submit Payment Details';
        return;
      }

      await Api.payment.manual({
        order_id:       order.order_id,
        payment_method: method,
        sender_number:  sender,
        transaction_id: txid,
        note:           note,
      });

      // Success — redirect to dashboard with pending notice
      toast('Payment submitted! Awaiting admin approval.', 'success');
      setTimeout(() => {
        location.href = 'dashboard.html?payment=pending';
      }, 1500);

    } catch (e) {
      toast(e.message, 'error');
      btn.disabled = false;
      btn.textContent = 'Submit Payment Details';
    }
  });
})();
