/* Catalogue: search, category filter, sort and paging — all server-side queries. */
(function initProducts() {
  const state = {
    category: Dx.queryParam('category') || '',
    q: '',
    sort: 'newest',
    page: 1,
    perPage: 12,
  };

  const add = function (product, button) { Dx.addToCart('product', product.id, button); };

  let debounce;

  async function load() {
    const grid = document.getElementById('product-grid');
    Dx.skeletonCards(grid, 6);

    const params = new URLSearchParams({
      per_page: String(state.perPage),
      page: String(state.page),
      sort: state.sort,
    });
    if (state.category) params.set('category', state.category);
    if (state.q) params.set('q', state.q);

    try {
      const response = await Dx.endpoints.products.list('?' + params.toString());
      const meta = response.meta || { total: response.products.length, page: 1, last_page: 1 };

      if (!response.products.length) {
        Dx.mount(grid, Dx.h('div', { class: 'empty' },
          Dx.h('h3', { text: 'Nothing matches that search' }),
          Dx.h('p', { class: 'muted', text: 'Try another keyword, or clear the category filter.' })
        ));
      } else {
        Dx.mount(grid, response.products.map(function (product) { return Dx.productCard(product, add); }));
        Dx.reveal(grid);
      }

      document.getElementById('result-count').textContent =
        meta.total + ' product' + (meta.total === 1 ? '' : 's') +
        (state.category ? ' in ' + state.category : '') +
        (state.q ? ' matching “' + state.q + '”' : '');

      renderPager(meta);
    } catch (error) {
      Dx.mount(grid, Dx.h('p', { class: 'banner banner-danger', text: error.message }));
    }
  }

  function renderPager(meta) {
    const pager = document.getElementById('pagination');
    if (meta.last_page <= 1) { Dx.clear(pager); return; }

    const goto = function (page) {
      state.page = page;
      load();
      window.scrollTo({ top: 0, behavior: Dx.reducedMotion ? 'auto' : 'smooth' });
    };

    Dx.mount(pager, [
      Dx.h('span', { text: 'Page ' + meta.page + ' of ' + meta.last_page }),
      Dx.h('div', { class: 'pager' },
        Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', disabled: meta.page <= 1, onclick: function () { goto(meta.page - 1); } }, 'Previous'),
        Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', disabled: meta.page >= meta.last_page, onclick: function () { goto(meta.page + 1); } }, 'Next')
      )
    ]);
  }

  async function loadCategories() {
    try {
      const response = await Dx.endpoints.categories();
      const chips = [Dx.h('button', { class: 'chip', type: 'button', dataset: { category: '' } }, 'All products')];

      response.categories.forEach(function (category) {
        chips.push(Dx.h('button', { class: 'chip', type: 'button', dataset: { category: category.slug } },
          category.name,
          Dx.h('span', { class: 'faint', text: String(category.product_count) })
        ));
      });

      Dx.mount('category-chips', chips);
      syncChips();

      chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
          state.category = chip.dataset.category;
          state.page = 1;
          syncChips();
          load();
        });
      });
    } catch (error) { /* chips are optional */ }
  }

  function syncChips() {
    document.querySelectorAll('#category-chips .chip').forEach(function (chip) {
      chip.setAttribute('aria-pressed', String(chip.dataset.category === state.category));
    });
  }

  document.getElementById('search-input').addEventListener('input', function (event) {
    clearTimeout(debounce);
    debounce = setTimeout(function () {
      state.q = event.target.value.trim();
      state.page = 1;
      load();
    }, 280);
  });

  document.getElementById('sort-select').addEventListener('change', function (event) {
    state.sort = event.target.value;
    state.page = 1;
    load();
  });

  document.getElementById('footer-year').textContent = String(new Date().getFullYear());

  (async function boot() {
    const user = await Dx.currentUser();
    Dx.renderAuthArea(user);
    if (user) Dx.refreshCartBadge();
    loadCategories();
    load();
  })();
})();
