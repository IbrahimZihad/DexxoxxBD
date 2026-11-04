/* Passes: bundle plans first, then the cheapest single-seat products. */
(function initPlans() {
  async function loadPlans() {
    Dx.skeletonCards('plan-grid', 3, 'plan-card');

    try {
      const { plans } = await Dx.endpoints.plans.list();
      const grid = document.getElementById('plan-grid');
      Dx.clear(grid);

      if (!plans.length) {
        Dx.mount(grid, Dx.h('div', { class: 'empty', text: 'No bundles on offer right now — individual products are still available below.' }));
        return;
      }

      plans.forEach(function (plan) {
        const card = Dx.planCard(plan, function (item, button) { Dx.addToCart('plan', item.id, button); });
        card.classList.add('tilt');
        grid.appendChild(card);
      });

      Dx.reveal(grid);
      Dx.tilt('#plan-grid .tilt');
    } catch (error) {
      Dx.mount('plan-grid', Dx.h('div', { class: 'banner banner-danger', text: error.message }));
    }
  }

  async function loadSingles() {
    try {
      const response = await Dx.endpoints.products.list('?in_stock=1&sort=popular&per_page=8');
      const products = response.products.slice(0, 8);

      if (!products.length) {
        document.getElementById('single-seat-section').classList.add('hide');
        return;
      }

      Dx.mount('single-grid', products.map(function (product) {
        return Dx.productCard(product, function (item, button) { Dx.addToCart('product', item.id, button); });
      }));
      Dx.reveal(document.getElementById('single-grid'));
    } catch (error) {
      /* the bundle grid already carries the page */
    }
  }

  (async function boot() {
    const user = await Dx.currentUser();
    Dx.renderAuthArea(user);
    if (user) Dx.refreshCartBadge();
    document.getElementById('footer-year').textContent = String(new Date().getFullYear());

    try {
      const { settings } = await Dx.endpoints.settings.public();
      if (settings.site_tagline) Dx.mount('plans-intro', settings.site_tagline);
    } catch (error) { /* intro copy is decorative */ }

    await loadPlans();
    loadSingles();
  })();
})();
