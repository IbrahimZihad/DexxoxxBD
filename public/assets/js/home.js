const CATEGORY_ICONS = {
  streaming: '🎬', music: '🎧', software: '🛠️', gaming: '🎮', education: '📚',
};

function productCard(p) {
  const discount = p.compare_at_price ? Math.round((1 - p.price / p.compare_at_price) * 100) : null;
  return `
  <div class="pass-card p-5 flex flex-col hover:shadow-lg transition">
    <div class="aspect-[4/3] rounded-lg overflow-hidden bg-line mb-4">
      <img src="${p.image_url || 'https://placehold.co/400x300'}" alt="${p.name}" class="w-full h-full object-cover" loading="lazy">
    </div>
    <p class="text-xs uppercase tracking-widest text-inksoft font-mono">${p.category_name || 'Digital'}</p>
    <h3 class="font-display font-semibold mt-1 leading-snug">${p.name}</h3>
    <div class="pass-perforation my-3"></div>
    <div class="flex items-end justify-between mt-auto">
      <div>
        <p class="font-display text-lg font-bold">${money(p.price)}</p>
        ${p.compare_at_price ? `<p class="text-xs text-inksoft line-through">${money(p.compare_at_price)}</p>` : ''}
      </div>
      ${discount ? `<span class="stamp text-teal" style="color:#1F9C8B">-${discount}%</span>` : ''}
    </div>
    <a href="product-detail.html?slug=${p.slug}" class="mt-4 block text-center bg-navy text-white text-sm font-semibold py-2.5 rounded-full hover:bg-ink transition">View pass</a>
  </div>`;
}

(async function loadHome() {
  try {
    const { products } = await Api.products('?featured=1');
    document.getElementById('featured-products').innerHTML = products.slice(0, 4).map(productCard).join('') || `<p class="text-inksoft col-span-4">No featured products yet.</p>`;
  } catch (e) { console.error(e); }

  const categories = [
    { name: 'Streaming', slug: 'streaming' },
    { name: 'Music', slug: 'music' },
    { name: 'Software', slug: 'software' },
    { name: 'Gaming', slug: 'gaming' },
    { name: 'Education', slug: 'education' },
  ];
  document.getElementById('category-strip').innerHTML = categories.map(c => `
    <a href="products.html?category=${c.slug}" class="pass-card p-5 text-center hover:shadow-lg transition">
      <div class="text-3xl mb-2">${CATEGORY_ICONS[c.slug] || '✨'}</div>
      <p class="font-semibold text-sm">${c.name}</p>
    </a>`).join('');
})();
