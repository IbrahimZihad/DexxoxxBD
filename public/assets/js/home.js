/* Homepage: chrome, featured tiles, categories, live counters. */
(async function initHome() {
  const user = await Dx.currentUser();
  Dx.renderAuthArea(user);
  if (user) Dx.refreshCartBadge();

  document.getElementById('footer-year').textContent = String(new Date().getFullYear());

  const add = function (product, button) { Dx.addToCart('product', product.id, button); };

  Dx.endpoints.settings.public().then(function (response) {
    const settings = response.settings || {};
    const bar = document.getElementById('announcement');
    const track = document.getElementById('announcement-track');

    if (settings.announcement_bar) {
      Dx.mount(track, [settings.announcement_bar, settings.announcement_bar].map(function (text) {
        return Dx.h('span', { text: text });
      }));
      bar.classList.remove('hide');
    }
  }).catch(function () { /* announcement is decorative */ });

  Dx.skeletonCards('featured-grid', 4);

  try {
    const [featured, categories] = await Promise.all([
      Dx.endpoints.products.list('?featured=1&per_page=8&sort=popular'),
      Dx.endpoints.categories(),
    ]);

    Dx.mount('featured-grid', featured.products.map(function (product) {
      return Dx.productCard(product, add);
    }));
    Dx.reveal(document.getElementById('featured-grid'));

    Dx.mount('category-chips', categories.categories.map(function (category) {
      return Dx.h('a', { class: 'chip', href: 'products.html?category=' + encodeURIComponent(category.slug) },
        Dx.h('span', { class: 'mono', text: category.name }),
        Dx.h('span', { class: 'faint', text: String(category.product_count) })
      );
    }));

    Dx.countUp(document.getElementById('stat-categories'), categories.categories.length);

    // "products live" is the whole catalogue; "units sold" sums the per-product
    // counters the admin keeps, so both come from the same paging walk.
    let page = 1;
    let sold = 0;
    let live = 0;
    let last = 1;
    do {
      const chunk = await Dx.endpoints.products.list('?per_page=60&page=' + page);
      live = Number(chunk.meta.total || 0);
      sold += chunk.products.reduce(function (sum, product) { return sum + Number(product.sold_count || 0); }, 0);
      last = chunk.meta.last_page;
      page++;
    } while (page <= last);
    Dx.countUp(document.getElementById('stat-products'), live);
    Dx.countUp(document.getElementById('stat-sold'), sold);
  } catch (error) {
    Dx.mount('featured-grid', Dx.h('p', { class: 'muted', text: error.message }));
  }

  if (!Dx.reducedMotion) {
    const progress = document.getElementById('hero-progress');
    if (progress) progress.style.setProperty('--w', '68%');

    const orbit = document.querySelector('.hero-orbit');
    orbit.addEventListener('pointermove', function (event) {
      const rect = orbit.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width - 0.5;
      const y = (event.clientY - rect.top) / rect.height - 0.5;
      document.getElementById('hero-card-a').style.setProperty('--shift-x', (x * 14).toFixed(1) + 'px');
      document.getElementById('hero-card-a').style.setProperty('--shift-y', (y * 14).toFixed(1) + 'px');
      document.getElementById('hero-card-b').style.setProperty('--shift-x', (x * -22).toFixed(1) + 'px');
      document.getElementById('hero-card-b').style.setProperty('--shift-y', (y * -22).toFixed(1) + 'px');
    });
  }
})();
