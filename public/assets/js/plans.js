function planCard(p, featured) {
  return `
  <div class="pass-card p-8 flex flex-col ${featured ? 'ring-2 ring-gold shadow-xl md:-translate-y-3' : ''}">
    ${p.badge ? `<span class="stamp text-gold w-fit" style="color:#7A4E0E">${p.badge}</span>` : ''}
    <h3 class="font-display text-2xl font-bold mt-4">${p.name}</h3>
    <p class="text-inksoft text-sm mt-2">${p.description || ''}</p>
    <div class="pass-perforation my-6"></div>
    <p class="font-display text-4xl font-bold">${money(p.price)}</p>
    <p class="text-inksoft text-sm mb-6">per ${p.duration_days >= 300 ? 'year' : p.duration_days + ' days'}</p>
    <ul class="space-y-3 text-sm flex-1">
      ${(p.features || []).map(f => `
        <li class="flex items-start gap-2">
          <span class="text-teal mt-0.5" style="color:#1F9C8B">✓</span><span>${f}</span>
        </li>`).join('')}
    </ul>
    <button data-id="${p.id}" class="add-plan-btn mt-8 w-full ${featured ? 'bg-gold text-goldink' : 'bg-navy text-white'} font-semibold py-3 rounded-full hover:brightness-95 transition">Choose ${p.name}</button>
  </div>`;
}

(async function loadPlans() {
  const grid = document.getElementById('plans-grid');
  try {
    const { plans } = await Api.plans();
    grid.innerHTML = plans.map(p => planCard(p, p.badge === 'Most Popular')).join('') || `<p class="text-inksoft col-span-3 text-center">No passes available right now.</p>`;

    grid.querySelectorAll('.add-plan-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        try {
          await Api.cart.add('plan', Number(btn.dataset.id), 1);
          toast('Pass added to cart.', 'success');
          initNav();
        } catch (e) {
          if (e.status === 401) { toast('Please sign in first.', 'error'); setTimeout(() => location.href = 'login.html', 800); }
          else toast(e.message, 'error');
        }
      });
    });
  } catch (e) {
    grid.innerHTML = `<p class="text-danger col-span-3 text-center">${e.message}</p>`;
  }
})();
