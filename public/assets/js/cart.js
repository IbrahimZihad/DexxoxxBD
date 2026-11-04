/* Cart: every price shown here is the server's, this page only sends ids and quantities. */
(function initCart() {
  let items = [];
  let subtotal = 0;

  function lineRow(item) {
    const media = Dx.h('div', { class: 'cart-line-thumb' });
    if (item.image) media.appendChild(Dx.h('img', { src: Dx.asset(item.image), alt: '', loading: 'lazy' }));
    else media.appendChild(Dx.h('span', { class: 'avatar avatar-gold', text: Dx.initials(item.name) }));

    const qty = Dx.h('div', { class: 'qty' });
    const output = Dx.h('output', { text: String(item.quantity), 'aria-label': item.name + ' quantity' });

    const send = function (next, button) {
      if (next === item.quantity || next < 1) return;
      Dx.setBusy(button, true, '…');
      Dx.endpoints.cart.update(item.cart_item_id, { quantity: next })
        .then(draw)
        .catch(function (error) {
          Dx.setBusy(button, false);
          Dx.toast(error.message, 'error');
        });
    };

    qty.append(
      Dx.h('button', { type: 'button', 'aria-label': 'Decrease ' + item.name, onclick: function () { send(item.quantity - 1, this); } }, '−'),
      output,
      Dx.h('button', { type: 'button', 'aria-label': 'Increase ' + item.name, onclick: function () { send(item.quantity + 1, this); } }, '+')
    );

    if (!item.available) Dx.clear(qty);

    const title = Dx.h('h3', { class: 'cell-title' }, item.slug
      ? Dx.h('a', { class: 'link-strong', href: 'product-detail.html?slug=' + encodeURIComponent(item.slug) }, item.name)
      : Dx.h('span', { text: item.name })
    );

    return Dx.h('div', { class: 'cart-line' + (item.available ? '' : ' is-unavailable') },
      media,
      Dx.h('div', { class: 'stack-tight' },
        title,
        Dx.h('div', { class: 'cluster' },
          Dx.h('span', { class: 'tile-meta mono', text: (item.type === 'plan' ? 'Bundle' : 'Pass') + ' · ' + item.duration_days + ' days' }),
          item.available ? null : Dx.h('span', { class: 'stamp stamp-danger' }, 'Unavailable')
        )
      ),
      Dx.h('div', { class: 'spacer' }),
      qty,
      Dx.h('div', { class: 'stack-tight text-right' },
        Dx.h('span', { class: 'price', text: Dx.money(item.line_total) }),
        Dx.h('span', { class: 'tile-meta', text: Dx.money(item.unit_price) + ' each' })
      ),
      Dx.h('button', {
        class: 'icon-btn', type: 'button', 'aria-label': 'Remove ' + item.name,
        onclick: function () {
          Dx.endpoints.cart.remove(item.cart_item_id).then(draw).catch(function (error) { Dx.toast(error.message, 'error'); });
        }
      }, Dx.h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'aria-hidden': 'true' },
        Dx.h('path', { d: 'M3 6h18M8 6V4h8v2m-9 0 1 14h6l1-14' }))
      )
    );
  }

  function draw(snapshot) {
    items = snapshot.items;
    subtotal = Number(snapshot.subtotal);

    // The header badge has to follow every redraw, including the one that empties the cart.
    const badge = document.getElementById('cart-badge');
    if (badge) {
      badge.textContent = String(snapshot.count);
      badge.classList.toggle('hide', !snapshot.count);
    }

    const body = document.getElementById('cart-body');
    Dx.clear(body);

    if (!items.length) {
      body.append(Dx.h('div', { class: 'empty' },
        Dx.h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.6', 'stroke-linecap': 'round', 'aria-hidden': 'true' },
          Dx.h('path', { d: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13 5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' })),
        Dx.h('h3', { text: 'Nothing in the cart yet' }),
        Dx.h('p', { class: 'muted', text: 'Add a bundle or pick a single service — activation is instant either way.' }),
        Dx.h('div', { class: 'cluster' },
          Dx.h('a', { class: 'btn btn-primary', href: 'plans.html' }, 'See passes'),
          Dx.h('a', { class: 'btn btn-ghost', href: 'products.html' }, 'Browse products')
        )
      ));
      Dx.clear('cart-summary');
      document.getElementById('clear-cart').classList.add('hide');
      document.getElementById('cart-note').classList.add('hide');
      return;
    }

    body.append(Dx.h('div', { class: 'stack' }, items.map(lineRow)));

    const blocked = items.some(function (item) { return !item.available; });
    const checkout = Dx.h('a', { class: 'btn btn-gold btn-lg btn-block', href: blocked ? '#cart-body' : 'checkout.html' },
      blocked ? 'Remove unavailable items' : 'Checkout · ' + Dx.money(subtotal)
    );
    if (blocked) checkout.addEventListener('click', function (event) { event.preventDefault(); Dx.toast('Remove the greyed-out items to continue.', 'error'); });

    Dx.mount('cart-summary', Dx.h('div', { class: 'panel stack' },
      Dx.h('h2', { class: 'display' }, 'Order summary'),
      Dx.h('div', { class: 'stack-tight' },
        Dx.h('div', { class: 'summary-line' }, Dx.h('span', { text: 'Items' }), Dx.h('span', { class: 'mono', text: String(snapshot.count) })),
        Dx.h('div', { class: 'summary-line' }, Dx.h('span', { text: 'Subtotal' }), Dx.h('span', { class: 'mono', text: Dx.money(subtotal) })),
        Dx.h('div', { class: 'summary-line' }, Dx.h('span', { text: 'Activation' }), Dx.h('span', { class: 'mono', text: 'Free' }))
      ),
      Dx.h('div', { class: 'summary-total' }, Dx.h('span', { text: 'Total' }), Dx.h('span', { text: Dx.money(subtotal) })),
      checkout,
      Dx.h('p', { class: 'hint', text: 'Pay by card, bKash, Nagad, Rocket or bank transfer on the next step.' })
    ));

    document.getElementById('clear-cart').classList.toggle('hide', false);
  }

  function load() {
    return Dx.endpoints.cart.list().then(draw).catch(function (error) {
      Dx.mount('cart-body', Dx.h('div', { class: 'banner banner-danger', text: error.message }));
    });
  }

  (async function boot() {
    const user = await Dx.requireUser('login.html?next=' + encodeURIComponent('cart.html'));
    Dx.renderAuthArea(user);
    document.getElementById('footer-year').textContent = String(new Date().getFullYear());

    Dx.skeletonCards('cart-body', 2, 'skeleton-line');
    Dx.mount('cart-summary', Dx.h('div', { class: 'panel' }, Dx.h('div', { class: 'skeleton skeleton-text' }), Dx.h('div', { class: 'skeleton skeleton-cell' })));

    document.getElementById('clear-cart').addEventListener('click', function () {
      Dx.setBusy(this, true, 'Emptying…');
      Dx.endpoints.cart.clear()
        .then(function () { Dx.toast('Cart emptied.'); Dx.refreshCartBadge(); draw({ items: [], subtotal: 0, count: 0 }); })
        .catch(function (error) { Dx.setBusy(this, false); Dx.toast(error.message, 'error'); }.bind(this));
    });

    await load();
  })();
})();
