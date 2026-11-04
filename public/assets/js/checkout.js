/* Checkout: turn the cart into an order, then hand the buyer a payment channel.
   The amount never comes from the browser — the order is priced server-side. */
(function initCheckout() {
  let user = null;
  let order = null;
  let methods = null;
  let selectedChannel = null;

  const banners = {
    success: ['banner-teal', 'Payment confirmed. Your pass code is in your dashboard.'],
    failed: ['banner-danger', 'The gateway did not confirm that payment. Your order is still pending — try again or pay manually.'],
    cancelled: ['banner-warn', 'You cancelled the gateway session. Nothing was charged; the order is still pending.'],
    invalid: ['banner-danger', 'That payment return was incomplete. Please retry from your order.'],
    manual: ['banner-teal', 'Payment details received. We verify manual payments within a few hours.'],
  };

  /* ---------------- chrome ---------------- */
  function showBanner() {
    const flag = Dx.queryParam('payment');
    if (!flag) return;
    const preset = banners[flag] || ['banner', 'Payment status: ' + flag];
    Dx.mount('payment-banner', Dx.h('div', { class: 'banner ' + preset[0] },
      Dx.h('strong', { text: preset[1] }),
      Dx.h('span', { class: 'spacer' }),
      Dx.h('a', { class: 'btn btn-quiet btn-sm', href: 'dashboard.html' }, 'Go to dashboard')
    ));
  }

  function summary(lines, total, extra) {
    const node = Dx.h('div', { class: 'stack' },
      Dx.h('h2', { class: 'display' }, 'Summary'),
      Dx.h('div', { class: 'stack-tight' }, lines.map(function (line) {
        return Dx.h('div', { class: 'summary-line' }, Dx.h('span', { text: line[0] }), Dx.h('span', { class: 'mono', text: line[1] }));
      })),
      Dx.h('div', { class: 'summary-total' }, Dx.h('span', { text: 'Total' }), Dx.h('span', { text: Dx.money(total) })),
      extra || null,
      Dx.h('ul', { class: 'stack-tight hint' }, [
        'Reserved stock is released if the order expires unpaid.',
        'Pass codes appear in your dashboard the moment payment clears.',
        'Never send money before the order number above exists.',
      ].map(function (text) { return Dx.h('li', { text: '· ' + text }); }))
    );
    Dx.mount('checkout-summary', node);
  }

  function field(name, label, type, value, hint, autocomplete) {
    return Dx.h('label', { class: 'field' },
      Dx.h('span', { class: 'label', text: label }),
      Dx.h('input', { class: 'input', name: name, type: type, value: value || '', autocomplete: autocomplete || 'off', inputmode: type === 'tel' ? 'numeric' : null }),
      hint ? Dx.h('span', { class: 'hint', text: hint }) : null,
      Dx.h('span', { class: 'error-text', 'data-for': name })
    );
  }

  /* ---------------- step 1: contact + place order ---------------- */
  function contactForm(cart) {
    Dx.mount('contact-body', Dx.h('form', { id: 'place-order', class: 'stack', novalidate: 'novalidate' },
      Dx.h('div', { class: 'row-2' },
        field('customer_name', 'Full name', 'text', user.name, 'As it should appear on the receipt.', 'name'),
        field('customer_phone', 'Mobile number', 'tel', user.phone, 'Bangladeshi number, e.g. 01712345678.', 'tel')
      ),
      field('customer_email', 'Email', 'email', user.email, 'Your pass code is also listed in the dashboard.', 'email'),
      Dx.h('button', { class: 'btn btn-primary btn-lg btn-block', type: 'submit' }, 'Place order · ' + Dx.money(cart.subtotal))
    ));

    document.getElementById('place-order').addEventListener('submit', function (event) {
      event.preventDefault();
      const button = event.submitter || this.querySelector('button');
      Dx.setBusy(button, true, 'Reserving stock…');

      Dx.endpoints.orders.create({
        customer_name: this.customer_name.value,
        customer_email: this.customer_email.value,
        customer_phone: this.customer_phone.value,
      }).then(function (created) {
        order = created;
        Dx.refreshCartBadge();
        Dx.toast('Order ' + created.order_number + ' reserved.', 'success');
        return openPayment();
      }).catch(function (error) {
        Dx.setBusy(button, false);
        Dx.fieldErrors(error);
        Dx.toast(error.message, 'error');
      });
    });
  }

  /* ---------------- step 2: payment ---------------- */
  function openPayment() {
    document.getElementById('order-contact').classList.add('hide');
    document.getElementById('pay-panel').classList.remove('hide');
    return loadMethods();
  }

  async function loadMethods() {
    try {
      methods = await Dx.endpoints.payment.methods();
    } catch (error) {
      Dx.mount('pay-methods', Dx.h('div', { class: 'banner banner-danger', text: error.message }));
      return;
    }

    Dx.mount('pay-amount', [
      Dx.h('span', { class: 'mono', text: (order.order_number || '') }),
      ' · ',
      Dx.h('strong', { class: 'mono', text: Dx.money(order.total_amount) }),
      order.expires_at ? Dx.h('span', { class: 'faint', text: ' · hold expires ' + Dx.formatDateTime(order.expires_at) }) : null
    ]);

    const nodes = [];

    if (methods.gateway && methods.gateway.available) {
      nodes.push(Dx.h('button', {
        class: 'pay-method', type: 'button', dataset: { channel: 'gateway' },
        onclick: function () { selectChannel('gateway', this); }
      },
        Dx.h('strong', { text: 'Card / Net Banking' }),
        Dx.h('span', { text: 'SSLCommerz · instant activation' }),
        methods.gateway.sandbox ? Dx.h('span', { class: 'pill pill-teal', text: 'Sandbox' }) : null
      ));
    }

    (methods.manual && methods.manual.channels ? methods.manual.channels : []).forEach(function (channel) {
      nodes.push(Dx.h('button', {
        class: 'pay-method', type: 'button', dataset: { channel: channel.method },
        onclick: function () { selectChannel(channel.method, this); }
      },
        Dx.h('strong', { text: channel.label }),
        Dx.h('span', { text: channel.kind + ' · ' + channel.number })
      ));
    });

    if (!nodes.length) {
      Dx.mount('pay-methods', Dx.h('div', { class: 'banner banner-warn', text: 'No payment channel is open right now. Please contact support.' }));
      return;
    }

    Dx.mount('pay-methods', Dx.h('div', { class: 'pay-methods' }, nodes));
    Dx.mount('pay-detail', Dx.h('p', { class: 'muted', text: 'Choose a channel to see where to send the money.' }));
  }

  function selectChannel(key, button) {
    document.querySelectorAll('.pay-method').forEach(function (node) { node.classList.remove('is-selected'); });
    button.classList.add('is-selected');
    selectedChannel = key;

    if (key === 'gateway') return renderGateway();

    const channel = methods.manual.channels.filter(function (row) { return row.method === key; })[0];
    return renderManual(channel);
  }

  function renderGateway() {
    Dx.mount('pay-detail', Dx.h('div', { class: 'stack' },
      Dx.h('p', { class: 'muted', text: 'You will be handed to the SSLCommerz page to finish. Returning here re-verifies the transaction before your pass is issued.' }),
      Dx.h('button', {
        class: 'btn btn-primary btn-block btn-lg', type: 'button', id: 'gateway-pay',
        onclick: function () {
          Dx.setBusy(this, true, 'Opening gateway…');
          Dx.endpoints.payment.init(order.order_id).then(function (session) {
            if (!/^[a-z][a-z0-9+.-]*:/i.test(session.gateway_url) || !/^https?:/i.test(session.gateway_url)) {
              throw new Error('The gateway returned an unusable URL.');
            }
            location.href = session.gateway_url;
          }).catch(function (error) {
            Dx.setBusy(this, false);
            Dx.toast(error.message, 'error');
          }.bind(this));
        }
      }, 'Pay ' + Dx.money(order.total_amount) + ' securely')
    ));
  }

  function renderManual(channel) {
    const detail = Dx.h('div', { class: 'pay-detail stack-tight' },
      Dx.h('dl', null,
        Dx.h('dt', { text: 'Send to' }), Dx.h('dd', { text: channel.number }),
        Dx.h('dt', { text: 'Account type' }), Dx.h('dd', { text: channel.kind }),
        Dx.h('dt', { text: 'Amount' }), Dx.h('dd', { text: Dx.money(order.total_amount) }),
        channel.bank_name ? Dx.h('dt', { text: 'Bank' }) : null,
        channel.bank_name ? Dx.h('dd', { text: channel.bank_name + ' · ' + (channel.account || '') }) : null
      ),
      Dx.h('div', { class: 'cluster' },
        Dx.h('button', { class: 'copy-btn', type: 'button', onclick: function () { copy(channel.number, this); } }, 'Copy number')
      )
    );

    const form = Dx.h('form', { class: 'stack', novalidate: 'novalidate' },
      Dx.h('div', { class: 'row-2' },
        field('transaction_id', channel.label + ' / transaction ID', 'text', '', 'The TrxID from your payment message.'),
        field('sender_number', 'Sender number', 'tel', user.phone, 'The number you paid from.')
      ),
      Dx.h('label', { class: 'field' },
        Dx.h('span', { class: 'label', text: 'Note (optional)' }),
        Dx.h('textarea', { class: 'textarea', name: 'note', maxlength: '500', placeholder: 'Anything our team should know.' })
      ),
      Dx.h('button', { class: 'btn btn-gold btn-block btn-lg', type: 'submit' }, 'I have sent the money')
    );

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      const button = this.querySelector('button');
      Dx.setBusy(button, true, 'Sending…');

      Dx.endpoints.payment.manual({
        order_id: order.order_id,
        payment_method: channel.method,
        transaction_id: this.transaction_id.value,
        sender_number: this.sender_number.value,
        note: this.note.value,
      }).then(function (result) {
        Dx.mount('pay-detail', Dx.h('div', { class: 'banner banner-teal' },
          Dx.h('div', { class: 'stack-tight' },
            Dx.h('strong', { text: result.message }),
            Dx.h('span', { class: 'muted', text: 'Order ' + result.order_number + ' · reference ' + result.manual_payment_id }),
            Dx.h('a', { class: 'btn btn-quiet btn-sm', href: 'checkout.html?payment=manual' }, 'Track this order')
          )
        ));
        Dx.toast('Payment details submitted.', 'success');
      }).catch(function (error) {
        Dx.setBusy(button, false);
        Dx.fieldErrors(error);
        Dx.toast(error.message, 'error');
      });
    });

    Dx.mount('pay-detail', Dx.h('div', { class: 'stack' }, detail, form));
  }

  function copy(text, button) {
    if (!navigator.clipboard) { Dx.toast('Copy is not available in this browser.'); return; }
    navigator.clipboard.writeText(text).then(function () {
      button.textContent = 'Copied';
      setTimeout(function () { button.textContent = 'Copy number'; }, 1800);
    }).catch(function () { Dx.toast('Could not copy — please select the number manually.'); });
  }

  /* ---------------- resume a pending order ---------------- */
  async function resumePending() {
    try {
      const page = await Dx.endpoints.orders.list('?page=1');
      const pending = page.orders.filter(function (row) { return row.status === 'pending'; })[0];
      if (!pending) return false;

      const full = (await Dx.endpoints.orders.get(pending.id)).order;
      order = {
        order_id: full.id,
        order_number: full.order_number,
        total_amount: Number(full.total_amount),
        status: full.status,
        expires_at: full.expires_at,
      };

      document.getElementById('resume-panel').classList.remove('hide');
      Dx.mount('resume-body', Dx.h('div', { class: 'stack' },
        Dx.h('div', { class: 'detail-grid' },
          tile('Order', order.order_number),
          tile('Amount', Dx.money(order.total_amount)),
          tile('Status', Dx.h('span', { class: 'stamp stamp-gold', text: order.status })),
          tile('Hold until', order.expires_at ? Dx.formatDateTime(order.expires_at) : '—')
        ),
        (full.manual_payment && full.manual_payment.status === 'pending')
          ? Dx.h('div', { class: 'banner banner-warn', text: 'A payment for this order is already waiting for review. Nothing more to do — we will email you when it clears.' }
          )
          : Dx.h('div', { class: 'cluster' },
            Dx.h('button', { class: 'btn btn-quiet btn-sm', type: 'button', onclick: openPayment }, 'Choose another channel')
          ),
        Dx.h('a', { class: 'btn btn-ghost btn-sm', href: 'dashboard.html' }, 'See all my orders')
      ));

      await openPayment();
      return true;
    } catch (error) {
      return false;
    }
  }

  function tile(label, value) {
    const node = Dx.h('div', { class: 'detail' });
    node.append(Dx.h('dt', { text: label }), Dx.h('dd', {}, value));
    return node;
  }

  /* ---------------- boot ---------------- */
  (async function boot() {
    user = await Dx.requireUser('login.html?next=' + encodeURIComponent('checkout.html'));
    Dx.renderAuthArea(user);
    Dx.refreshCartBadge();
    showBanner();
    document.getElementById('footer-year').textContent = String(new Date().getFullYear());

    const flag = Dx.queryParam('payment');

    if (flag && flag !== 'success') {
      if (await resumePending()) return;
    }

    let cart;
    try {
      cart = await Dx.endpoints.cart.list();
    } catch (error) {
      Dx.mount('contact-body', Dx.h('div', { class: 'banner banner-danger', text: error.message }));
      return;
    }

    if (!cart.items.length) {
      const blocked = flag === 'success';
      Dx.mount('contact-body', Dx.h('div', { class: 'empty' },
        Dx.h('h3', { text: blocked ? 'Payment received — nothing left in the cart' : 'Your cart is empty' }),
        Dx.h('p', { class: 'muted', text: blocked
          ? 'This page is for new orders. Your paid pass is in the dashboard.'
          : 'Add a pass first and the checkout will open up.' }),
        Dx.h('div', { class: 'cluster' },
          Dx.h('a', { class: 'btn btn-primary', href: blocked ? 'dashboard.html' : 'products.html' }, blocked ? 'Go to my passes' : 'Browse products'),
          blocked ? null : Dx.h('a', { class: 'btn btn-ghost', href: 'plans.html' }, 'See bundles')
        )
      ));
      summary([['Items', '0'], ['Activation', 'Free']], 0, null);
      return;
    }

    contactForm(cart);
    summary(
      cart.items.map(function (item) { return [item.name + ' × ' + item.quantity, Dx.money(item.line_total)]; })
        .concat([['Activation', 'Free']]),
      cart.subtotal,
      Dx.h('p', { class: 'faint mono', text: 'Order is created when you place it.' })
    );
  })();
})();
