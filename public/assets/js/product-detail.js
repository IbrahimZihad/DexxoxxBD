/* Product detail: buy box with server-checked stock, plus related items. */
(function initProductDetail() {
  const slug = Dx.queryParam('slug') || '';
  const id = Dx.queryParam('id') || '';
  const query = slug ? '?slug=' + encodeURIComponent(slug) : '?id=' + encodeURIComponent(id || '0');

  let product = null;
  let quantity = 1;
  let stepper = null;

  function render() {
    document.title = product.name + ' — DexxoxxBD';

    Dx.mount('crumb-category', product.category || 'Digital');

    const media = Dx.h('figure', { class: 'card tile' },
      Dx.h('div', { class: 'tile-media detail-media' },
        product.image_url ? Dx.h('img', { src: product.image_url, alt: product.name }) : null
      )
    );
    Dx.mount('detail-media', media);

    const buyButton = Dx.h('button', { class: 'btn btn-primary btn-lg btn-block', type: 'button', disabled: !product.in_stock },
      product.in_stock ? 'Add to cart · ' + Dx.money(product.price * quantity) : 'Sold out'
    );
    buyButton.addEventListener('click', function () {
      Dx.addToCart('product', product.id, buyButton);
      buyButton.textContent = 'Add to cart · ' + Dx.money(product.price * quantity);
    });

    const checkoutButton = Dx.h('a', { class: 'btn btn-gold btn-lg btn-block', href: 'cart.html' }, 'Go to checkout');

    stepper = buildStepper();

    Dx.mount('detail-body', [
      Dx.h('div', { class: 'cluster' },
        product.is_featured ? Dx.h('span', { class: 'pill pill-teal' }, '★ Featured') : null,
        product.category ? Dx.h('span', { class: 'chip' , text: product.category }) : null,
        product.in_stock ? Dx.h('span', { class: 'stamp stamp-teal stamp-pulse' }, 'In stock') : Dx.h('span', { class: 'stamp stamp-danger' }, 'Sold out')
      ),
      Dx.h('h1', { class: 'display' }, product.name),
      Dx.h('div', { class: 'cluster' },
        Dx.h('span', { class: 'price' , text: Dx.money(product.price) }),
        product.compare_at_price ? Dx.h('span', { class: 'price-old' , text: Dx.money(product.compare_at_price) }) : null,
        product.discount_percent ? Dx.h('span', { class: 'discount' , text: 'SAVE ' + product.discount_percent + '%' }) : null
      ),
      Dx.h('p', { class: 'muted' }, product.description || ''),
      Dx.h('div', { class: 'divider' }),
      Dx.h('dl', { class: 'detail-grid' },
        detail('Duration', product.duration_days + ' days'),
        detail('Delivery', 'Pass code in dashboard'),
        detail('Sold', String(product.sold_count) + ' times'),
        detail('Product code', 'DXB-' + String(product.id).padStart(4, '0'))
      ),
      Dx.h('div', { class: 'cluster' }, Dx.h('span', { class: 'label' , text: 'Quantity' }), stepper),
      buyButton,
      product.in_stock ? checkoutButton : null
    ]);

    Dx.mount('detail-assurances', Dx.h('ul', { class: 'stack-tight' },
      ['Verified account — never shared with other buyers.',
        'Renew with one click before it expires.',
        'Not activated? We refund the full amount, no questions.',
        'Support every day, 10:00–22:00 (GMT+6).'].map(function (line) {
        return Dx.h('li', { class: 'cluster' },
          Dx.h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.5', 'stroke-linecap': 'round', 'aria-hidden': 'true', class: 'icon-check' }, Dx.h('path', { d: 'M20 6L9 17l-5-5' })),
          Dx.h('span', { text: line })
        );
      })
    ));

    loadRelated();
  }

  function detail(label, value) {
    const item = Dx.h('div', { class: 'detail' });
    item.append(Dx.h('dt', { text: label }), Dx.h('dd', { text: value }));
    return item;
  }

  function buildStepper() {
    const output = Dx.h('output', { text: String(quantity) });
    const update = function (next) {
      quantity = Math.min(20, Math.max(1, next));
      output.textContent = String(quantity);
      const button = document.querySelector('#detail-body .btn-primary');
      if (button && product) button.textContent = 'Add to cart · ' + Dx.money(product.price * quantity);
      output.classList.remove('is-dirty');
      void output.offsetWidth;
      output.classList.add('is-dirty');
    };

    return Dx.h('div', { class: 'qty' },
      Dx.h('button', { type: 'button', 'aria-label': 'Decrease quantity', onclick: function () { update(quantity - 1); } }, '−'),
      output,
      Dx.h('button', { type: 'button', 'aria-label': 'Increase quantity', onclick: function () { update(quantity + 1); } }, '+')
    );
  }

  async function loadRelated() {
    if (!product.category_slug) return;
    try {
      const response = await Dx.endpoints.products.list('?category=' + encodeURIComponent(product.category_slug) + '&per_page=4');
      const others = response.products.filter(function (item) { return item.id !== product.id; }).slice(0, 4);
      if (!others.length) return;

      Dx.mount('related-grid', others.map(function (item) {
        return Dx.h('article', { class: 'card card-hover tile reveal' },
          Dx.h('a', { href: 'product-detail.html?slug=' + encodeURIComponent(item.slug) },
            Dx.h('div', { class: 'tile-media' }, item.image_url ? Dx.h('img', { src: item.image_url, alt: '', loading: 'lazy' }) : null)
          ),
          Dx.h('div', { class: 'tile-body' },
            Dx.h('h3', { class: 'tile-title' }, Dx.h('a', { href: 'product-detail.html?slug=' + encodeURIComponent(item.slug) }, item.name)),
            Dx.h('p', { class: 'tile-desc' }, item.description || '')
          ),
          Dx.h('div', { class: 'tile-foot' }, Dx.h('span', { class: 'price', text: Dx.money(item.price) }))
        );
      }));
      Dx.reveal(document.getElementById('related-grid'));
    } catch (error) { /* related items are decorative */ }
  }

  (async function boot() {
    const user = await Dx.currentUser();
    Dx.renderAuthArea(user);
    if (user) Dx.refreshCartBadge();
    document.getElementById('footer-year').textContent = String(new Date().getFullYear());

    Dx.skeletonCards('detail-media', 1);

    try {
      product = (await Dx.endpoints.products.get(query)).product;
      render();
      Dx.reveal();
    } catch (error) {
      Dx.mount('detail-body', Dx.h('div', { class: 'banner banner-danger', text: error.message }));
      Dx.clear('detail-media');
    }
  })();
})();
