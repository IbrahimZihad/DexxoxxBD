/* Admin catalogue: create, edit, restock and retire products. */
(async function initAdminProducts() {
  await Admin.start('Products', 'Every single-service pass the shop sells.', 'products.html');

  let categories = [];
  let state = { q: '', category: '', status: '', stock: Dx.queryParam('stock') || '', page: 1 };

  Admin.outlet(Dx.h('div', { class: 'panel stack' },
    Dx.h('div', { class: 'filters' },
      Dx.h('input', { class: 'input', id: 'product-search', type: 'search', placeholder: 'Search by name…', autocomplete: 'off' }),
      Dx.h('select', { class: 'select', id: 'product-category' }),
      Dx.h('select', { class: 'select', id: 'product-status' },
        Dx.h('option', { value: '', text: 'Any status' }),
        Dx.h('option', { value: 'active', text: 'active only' }),
        Dx.h('option', { value: 'inactive', text: 'retired only' })
      ),
      Dx.h('select', { class: 'select', id: 'product-stock' },
        Dx.h('option', { value: '', text: 'Any stock level' }),
        Dx.h('option', { value: 'empty', text: 'Out of stock' }),
        Dx.h('option', { value: 'low', text: 'Low stock (5 or fewer)' })
      ),
      Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', id: 'product-clear' }, 'Clear')
    ),
    Dx.h('div', { class: 'table-wrap' }, Dx.h('table', { class: 'table' },
      Dx.h('thead', null, Dx.h('tr', null,
        ['Product', 'Category', 'Price', 'Days', 'Stock', 'Sold', 'Status', ''].map(function (label) {
          return Dx.h('th', { scope: 'col', text: label });
        })
      )),
      Dx.h('tbody', { id: 'product-body' })
    )),
    Dx.h('div', { class: 'pagination', id: 'product-pagination' })
  ));

  Admin.actions(Dx.h('button', { class: 'btn btn-gold btn-sm', type: 'button', id: 'new-product' }, '+ New product'));

  try {
    categories = (await Dx.endpoints.categories()).categories;
  } catch (error) { /* the form still works with "no category" */ }

  const categorySelect = document.getElementById('product-category');
  Dx.mount(categorySelect, [Dx.h('option', { value: '', text: 'All categories' })].concat(
    categories.map(function (row) { return Dx.h('option', { value: row.slug, text: row.name + ' · ' + row.product_count }); })
  ));

  const search = document.getElementById('product-search');
  const status = document.getElementById('product-status');
  const stock = document.getElementById('product-stock');
  let debounce = null;

  search.addEventListener('input', function () {
    clearTimeout(debounce);
    debounce = setTimeout(function () { state.q = search.value.trim(); state.page = 1; load(); }, 320);
  });
  categorySelect.addEventListener('change', function () { state.category = categorySelect.value; state.page = 1; load(); });
  status.addEventListener('change', function () { state.status = status.value; state.page = 1; load(); });
  stock.addEventListener('change', function () { state.stock = stock.value; state.page = 1; load(); });

  document.getElementById('product-clear').addEventListener('click', function () {
    state = { q: '', category: '', status: '', stock: '', page: 1 };
    search.value = '';
    categorySelect.value = '';
    status.value = '';
    stock.value = '';
    load();
  });

  document.getElementById('new-product').addEventListener('click', function () { openForm(null); });

  async function load() {
    Dx.skeletonRows('product-body', 8, 5);

    const params = new URLSearchParams({ page: String(state.page), per_page: '25' });
    if (state.q) params.set('q', state.q);
    if (state.category) params.set('category', state.category);
    if (state.status) params.set('status', state.status);
    if (state.stock) params.set('stock', state.stock);

    try {
      const data = await Dx.endpoints.products.list('?' + params.toString());
      const rows = data.products;

      const body = document.getElementById('product-body');
      Dx.clear(body);

      if (!rows.length) {
        body.appendChild(Dx.h('tr', null, Dx.h('td', { colspan: '8' },
          Dx.h('div', { class: 'empty', text: 'No product matches those filters.' }))));
        Dx.clear('product-pagination');
        return;
      }

      rows.forEach(function (row) {
        const stockBar = Dx.h('div', { class: 'progress-bar' + (row.stock === 0 ? ' is-low' : '') });
        stockBar.style.setProperty('--w', Math.min(100, Math.round((row.stock / 40) * 100)) + '%');

        body.appendChild(Dx.h('tr', null,
          Dx.h('td', null, Dx.h('div', { class: 'cell-title' },
            row.image_url ? Dx.h('img', { src: row.image_url, alt: '', loading: 'lazy' }) : Dx.h('span', { class: 'avatar', text: Dx.initials(row.name) }),
            Dx.h('span', null, Dx.h('span', { text: row.name }), Dx.h('span', { class: 'sub mono', text: row.slug }))
          )),
          Dx.h('td', { text: row.category || '—' }),
          Dx.h('td', { class: 'num', text: Dx.money(row.price) }),
          Dx.h('td', { class: 'num', text: String(row.duration_days) }),
          Dx.h('td', null, Dx.h('div', { class: 'mono', text: String(row.stock) }), Dx.h('div', { class: 'progress' }, stockBar)),
          Dx.h('td', { class: 'num', text: String(row.sold_count) }),
          Dx.h('td', null, Admin.stamp(row.status), row.is_featured ? Dx.h('span', { class: 'pill pill-teal', text: 'featured' }) : null),
          Dx.h('td', null, Dx.h('div', { class: 'actions' },
            Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', text: 'Edit', onclick: function () { openForm(row); } }),
            Dx.h('button', { class: 'btn btn-danger btn-sm', type: 'button', text: 'Delete', onclick: function () { destroy(row, this); } })
          ))
        ));
      });

      Admin.pager('product-pagination', data.meta, state.page, function (next) { state.page = next; load(); });
    } catch (error) {
      Dx.mount('product-body', Dx.h('tr', null, Dx.h('td', { colspan: '8' }, Dx.h('div', { class: 'banner banner-danger', text: error.message }))));
    }
  }

  function destroy(row, button) {
    Admin.confirmAction(
      'Delete "' + row.name + '" for good? Past orders keep their line text but lose the product link. Retire it instead if you may sell it again.',
      function () {
        return Dx.endpoints.products.remove(row.id).then(function () {
          Dx.toast('Product deleted.', 'success');
          load();
        });
      },
      button,
      'Deleting…'
    );
  }

  function fieldRow(label, control, hint, name) {
    return Dx.h('label', { class: 'field' },
      Dx.h('span', { class: 'label', text: label }),
      control,
      hint ? Dx.h('span', { class: 'hint', text: hint }) : null,
      Dx.h('span', { class: 'error-text', 'data-for': name })
    );
  }

  function openForm(product) {
    const editing = product !== null;

    const modal = Dx.h('div', { class: 'modal hide', id: 'product-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': editing ? 'Edit product' : 'New product' },
      Dx.h('div', { class: 'modal-box modal-box-wide' },
        Dx.h('div', { class: 'modal-head' },
          Dx.h('h2', { text: editing ? 'Edit ' + product.name : 'New product' }),
          Dx.h('button', { class: 'icon-btn', type: 'button', 'data-close-modal': 'product-modal', 'aria-label': 'Close' },
            Dx.h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'aria-hidden': 'true' },
              Dx.h('path', { d: 'M6 6l12 12M18 6 6 18' })))
        ),
        Dx.h('form', { id: 'product-form' },
          Dx.h('div', { class: 'row-2' },
            fieldRow('Name', Dx.h('input', { class: 'input', name: 'name', value: editing ? product.name : '', maxlength: '160', required: 'required' }), null, 'name'),
            fieldRow('Category', Dx.h('select', { class: 'select', name: 'category_id' },
              [Dx.h('option', { value: '', text: '— none —' })].concat(categories.map(function (row) {
                return Dx.h('option', { value: String(row.id), text: row.name, selected: editing && product.category_id === row.id ? 'selected' : null });
              }))
            ), 'Used for the storefront filters.', 'category_id')
          ),
          Dx.h('div', { class: 'row-2' },
            fieldRow('Price (৳)', Dx.h('input', { class: 'input', name: 'price', type: 'number', min: '1', step: '0.01', value: editing ? product.price : '', required: 'required' }), null, 'price'),
            fieldRow('Compare-at price (৳)', Dx.h('input', { class: 'input', name: 'compare_at_price', type: 'number', min: '1', step: '0.01', value: editing && product.compare_at_price ? product.compare_at_price : '' }), 'Shows a strike-through and a save badge.', 'compare_at_price')
          ),
          Dx.h('div', { class: 'row-2' },
            fieldRow('Duration (days)', Dx.h('input', { class: 'input', name: 'duration_days', type: 'number', min: '1', max: '3650', value: editing ? product.duration_days : '30' }), 'Length of the pass granted on purchase.', 'duration_days'),
            fieldRow('Stock (units)', Dx.h('input', { class: 'input', name: 'stock', type: 'number', min: '0', step: '1', value: editing ? product.stock : '10' }), 'Reserved when an order is placed.', 'stock')
          ),
          fieldRow('Image URL', Dx.h('input', { class: 'input', name: 'image_url', type: 'url', placeholder: 'https://…', value: editing && product.image_url ? product.image_url : '' }), 'A remote https link; leave empty for the gradient placeholder.', 'image_url'),
          fieldRow('Description', Dx.h('textarea', { class: 'textarea', name: 'description', maxlength: '5000' }, editing ? product.description : ''), null, 'description'),
          Dx.h('div', { class: 'cluster' },
            Dx.h('label', { class: 'switch' },
              Dx.h('input', { type: 'checkbox', name: 'status', checked: !editing || product.status === 'active' ? 'checked' : null }),
              Dx.h('span', { class: 'track' }), Dx.h('span', { text: 'Listed on the storefront' })
            ),
            Dx.h('label', { class: 'switch' },
              Dx.h('input', { type: 'checkbox', name: 'is_featured', checked: editing && product.is_featured ? 'checked' : null }),
              Dx.h('span', { class: 'track' }), Dx.h('span', { text: 'Featured on the homepage' })
            )
          ),
          Dx.h('div', { class: 'modal-actions' },
            Dx.h('button', { class: 'btn btn-quiet', type: 'button', 'data-close-modal': 'product-modal' }, 'Cancel'),
            Dx.h('button', { class: 'btn btn-primary', type: 'submit' }, editing ? 'Save changes' : 'Create product')
          )
        )
      )
    );

    document.body.appendChild(modal);
    Dx.openModal('product-modal');

    document.getElementById('product-form').addEventListener('submit', function (event) {
      event.preventDefault();
      const submit = event.target.querySelector('button[type="submit"]');
      const body = {
        name: this.name.value,
        category_id: this.category_id.value ? Number(this.category_id.value) : null,
        price: Number(this.price.value),
        compare_at_price: this.compare_at_price.value ? Number(this.compare_at_price.value) : null,
        duration_days: Number(this.duration_days.value),
        stock: Number(this.stock.value),
        image_url: this.image_url.value.trim() || null,
        description: this.description.value,
        status: this.status.checked ? 'active' : 'inactive',
        is_featured: this.is_featured.checked ? 1 : 0,
      };

      Dx.setBusy(submit, true, 'Saving…');

      const request = editing ? Dx.endpoints.products.update(product.id, body) : Dx.endpoints.products.create(body);

      request.then(function () {
        Dx.closeModal('product-modal');
        modal.remove();
        Dx.toast(editing ? 'Product updated.' : 'Product created.', 'success');
        load();
      }).catch(function (error) {
        Dx.setBusy(submit, false);
        Dx.fieldErrors(error);
        Dx.toast(error.message, 'error');
      });
    });
  }

  load();
})();
