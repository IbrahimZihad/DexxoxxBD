function cartRow(item) {
  return `
  <div class="pass-card p-4 flex items-center gap-4" data-cart-id="${item.cart_item_id}">
    <div class="w-16 h-16 rounded-lg overflow-hidden bg-line shrink-0">
      ${item.image ? `<img src="${item.image}" class="w-full h-full object-cover">` : `<div class="w-full h-full flex items-center justify-center text-xl">🎫</div>`}
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-xs uppercase tracking-widest text-inksoft font-mono">${item.type}</p>
      <p class="font-semibold truncate">${item.name}</p>
      <p class="text-sm text-inksoft">${money(item.unit_price)} · ${item.duration_days} days</p>
    </div>
    <div class="flex items-center gap-2">
      <button class="qty-btn w-8 h-8 rounded-full border border-line hover:border-ink" data-cart-id="${item.cart_item_id}" data-delta="-1">−</button>
      <span class="w-6 text-center font-mono">${item.quantity}</span>
      <button class="qty-btn w-8 h-8 rounded-full border border-line hover:border-ink" data-cart-id="${item.cart_item_id}" data-delta="1">+</button>
    </div>
    <p class="font-display font-semibold w-24 text-right">${money(item.line_total)}</p>
    <button class="remove-btn text-inksoft hover:text-danger" data-cart-id="${item.cart_item_id}" aria-label="Remove">✕</button>
  </div>`;
}

async function renderCart() {
  const wrap = document.getElementById('cart-items');
  try {
    const { items, total } = await Api.cart.list();
    wrap.innerHTML = items.length
      ? items.map(cartRow).join('')
      : `<div class="pass-card p-10 text-center text-inksoft">Your cart is empty. <a href="products.html" class="text-navy font-semibold hover:underline">Browse products →</a></div>`;

    document.getElementById('cart-subtotal').textContent = money(total);
    document.getElementById('cart-total').textContent = money(total);

    const checkoutBtn = document.getElementById('checkout-btn');
    if (!items.length) { checkoutBtn.classList.add('pointer-events-none', 'opacity-50'); }
    else { checkoutBtn.classList.remove('pointer-events-none', 'opacity-50'); }

    wrap.querySelectorAll('.qty-btn').forEach(btn => btn.addEventListener('click', async () => {
      const row = items.find(i => i.cart_item_id === Number(btn.dataset.cartId));
      const newQty = row.quantity + Number(btn.dataset.delta);
      if (newQty < 1) return;
      await Api.cart.update(btn.dataset.cartId, newQty);
      renderCart();
      initNav();
    }));

    wrap.querySelectorAll('.remove-btn').forEach(btn => btn.addEventListener('click', async () => {
      await Api.cart.remove(btn.dataset.cartId);
      toast('Removed from cart.', 'info');
      renderCart();
      initNav();
    }));
  } catch (e) {
    if (e.status === 401) {
      wrap.innerHTML = `<div class="pass-card p-10 text-center text-inksoft">Please <a href="login.html" class="text-navy font-semibold hover:underline">sign in</a> to view your cart.</div>`;
    } else {
      wrap.innerHTML = `<p class="text-danger">${e.message}</p>`;
    }
  }
}

document.addEventListener('DOMContentLoaded', renderCart);
