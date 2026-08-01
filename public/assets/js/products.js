function productGridCard(p) {
  const discount = p.compare_at_price ? Math.round((1 - p.price / p.compare_at_price) * 100) : null;
  return `
  <div class="pass-card p-5 flex flex-col hover:shadow-lg transition">
    <a href="product-detail.html?slug=${p.slug}" class="block aspect-[4/3] rounded-lg overflow-hidden bg-line mb-4">
      <img src="${p.image_url || 'https://placehold.co/400x300'}" alt="${p.name}" class="w-full h-full object-cover" loading="lazy">
    </a>
    <p class="text-xs uppercase tracking-widest text-inksoft font-mono">${p.category_name || 'Digital'}</p>
    <a href="product-detail.html?slug=${p.slug}"><h3 class="font-display font-semibold mt-1 leading-snug hover:underline">${p.name}</h3></a>
    <div class="pass-perforation my-3"></div>
    <div class="flex items-end justify-between mt-auto">
      <div>
        <p class="font-display text-lg font-bold">${money(p.price)}</p>
        ${p.compare_at_price ? `<p class="text-xs text-inksoft line-through">${money(p.compare_at_price)}</p>` : ''}
      </div>
      ${discount ? `<span class="stamp text-teal" style="color:#1F9C8B">-${discount}%</span>` : ''}
    </div>
    <button data-id="${p.id}" class="add-to-cart-btn mt-4 bg-navy text-white text-sm font-semibold py-2.5 rounded-full hover:bg-ink transition">Add to cart</button>
  </div>`;
}

async function loadProducts() {
  const grid = document.getElementById('product-grid');
  const params = new URLSearchParams(location.search);
  const q = document.getElementById('search-input').value.trim();
  const category = document.getElementById('category-select').value;

  const qs = new URLSearchParams();
  if (q) qs.set('q', q);
  if (category) qs.set('category', category);

  grid.innerHTML = `<p class="text-inksoft col-span-4">Loading products…</p>`;
  try {
    const { products } = await Api.products('?' + qs.toString());
    grid.innerHTML = products.length
      ? products.map(productGridCard).join('')
      : `<p class="text-inksoft col-span-4">No products match your search.</p>`;

    grid.querySelectorAll('.add-to-cart-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        try {
          await Api.cart.add('product', Number(btn.dataset.id), 1);
          toast('Added to cart.', 'success');
          initNav();
        } catch (e) {
          if (e.status === 401) { toast('Please sign in first.', 'error'); setTimeout(() => location.href = 'login.html', 800); }
          else toast(e.message, 'error');
        }
      });
    });
  } catch (e) {
    grid.innerHTML = `<p class="text-danger col-span-4">${e.message}</p>`;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(location.search);
  if (params.get('category')) document.getElementById('category-select').value = params.get('category');

  loadProducts();
  document.getElementById('category-select').addEventListener('change', loadProducts);
  let t;
  document.getElementById('search-input').addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadProducts, 350); });
});
