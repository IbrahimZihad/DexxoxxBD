(async function loadProductDetail() {
  const root = document.getElementById('product-detail-root');
  const slug = new URLSearchParams(location.search).get('slug');
  if (!slug) { root.innerHTML = `<p class="text-danger">No product specified.</p>`; return; }

  try {
    const { product: p } = await Api.product(`?slug=${encodeURIComponent(slug)}`);
    const discount = p.compare_at_price ? Math.round((1 - p.price / p.compare_at_price) * 100) : null;

    root.innerHTML = `
      <a href="products.html" class="text-sm text-inksoft hover:text-ink">← Back to products</a>
      <div class="grid md:grid-cols-2 gap-12 mt-6">
        <div class="rounded-2xl overflow-hidden bg-line aspect-[4/3]">
          <img src="${p.image_url || 'https://placehold.co/600x450'}" alt="${p.name}" class="w-full h-full object-cover">
        </div>
        <div>
          <p class="text-xs uppercase tracking-widest text-inksoft font-mono">${p.category_name || 'Digital'}</p>
          <h1 class="font-display text-3xl font-bold mt-2">${p.name}</h1>
          <p class="text-inksoft mt-4 leading-relaxed">${p.description || 'No description provided.'}</p>

          <div class="pass-card p-6 mt-8">
            <div class="flex items-end justify-between">
              <div>
                <p class="text-xs text-inksoft">Price</p>
                <p class="font-display text-3xl font-bold">${money(p.price)}</p>
              </div>
              ${p.compare_at_price ? `<div class="text-right"><p class="text-xs text-inksoft line-through">${money(p.compare_at_price)}</p><span class="stamp text-teal" style="color:#1F9C8B">Save ${discount}%</span></div>` : ''}
            </div>
            <div class="pass-perforation my-5"></div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-inksoft">Access length</span>
              <span class="font-semibold">${p.duration_days} days</span>
            </div>
            <div class="flex items-center gap-3 mt-6">
              <input id="qty-input" type="number" min="1" value="1" class="w-20 border border-line rounded-full px-4 py-2 text-center text-sm">
              <button id="add-to-cart-btn" class="flex-1 bg-navy text-white font-semibold py-3 rounded-full hover:bg-ink transition">Add to cart</button>
            </div>
          </div>
        </div>
      </div>
    `;

    document.getElementById('add-to-cart-btn').addEventListener('click', async () => {
      const qty = Math.max(1, Number(document.getElementById('qty-input').value) || 1);
      try {
        await Api.cart.add('product', p.id, qty);
        toast('Added to cart.', 'success');
        initNav();
      } catch (e) {
        if (e.status === 401) { toast('Please sign in first.', 'error'); setTimeout(() => location.href = 'login.html', 800); }
        else toast(e.message, 'error');
      }
    });
  } catch (e) {
    root.innerHTML = `<p class="text-danger">${e.message}</p>`;
  }
})();
