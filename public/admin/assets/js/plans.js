/* Admin bundles: multi-service passes with a feature list. */
(async function initAdminPlans() {
  await Admin.start('Bundles', 'Multi-service passes shown on the Passes page.', 'plans.html');

  Admin.outlet(Dx.h('div', { class: 'panel stack' },
    Dx.h('div', { class: 'table-wrap' }, Dx.h('table', { class: 'table' },
      Dx.h('thead', null, Dx.h('tr', null,
        ['Bundle', 'Price', 'Duration', 'Features', 'Order', 'Status', ''].map(function (label) {
          return Dx.h('th', { scope: 'col', text: label });
        })
      )),
      Dx.h('tbody', { id: 'plan-body' })
    )),
    Dx.h('div', { class: 'pagination', id: 'plan-pagination' })
  ));

  Admin.actions(Dx.h('button', { class: 'btn btn-gold btn-sm', type: 'button', id: 'new-plan' }, '+ New bundle'));

  let state = { page: 1 };

  document.getElementById('new-plan').addEventListener('click', function () { openForm(null); });

  async function load() {
    Dx.skeletonRows('plan-body', 7, 3);

    try {
      const data = await Dx.endpoints.plans.list('?page=' + state.page + '&per_page=25');
      const body = document.getElementById('plan-body');
      Dx.clear(body);

      if (!data.plans.length) {
        body.appendChild(Dx.h('tr', null, Dx.h('td', { colspan: '7' },
          Dx.h('div', { class: 'empty', text: 'No bundles yet. Customers can still buy single products.' }))));
        Dx.clear('plan-pagination');
        return;
      }

      data.plans.forEach(function (row) {
        body.appendChild(Dx.h('tr', null,
          Dx.h('td', null, Dx.h('div', { class: 'cell-title' },
            Dx.h('span', null,
              Dx.h('span', { text: row.name }),
              Dx.h('span', { class: 'sub mono', text: row.slug }),
              row.badge ? Dx.h('span', { class: 'pill', text: row.badge }) : null
            ))),
          Dx.h('td', { class: 'num', text: Dx.money(row.price) }),
          Dx.h('td', { class: 'num', text: row.duration_days + ' d' }),
          Dx.h('td', { class: 'faint', text: row.features.length + ' listed' }),
          Dx.h('td', { class: 'num', text: String(row.sort_order) }),
          Dx.h('td', null, Admin.stamp(row.status)),
          Dx.h('td', null, Dx.h('div', { class: 'actions' },
            Dx.h('a', { class: 'btn btn-quiet btn-sm', href: '../plans.html', target: '_blank' }, 'Preview'),
            Dx.h('button', { class: 'btn btn-ghost btn-sm', type: 'button', text: 'Edit', onclick: function () { openForm(row); } }),
            Dx.h('button', { class: 'btn btn-danger btn-sm', type: 'button', text: 'Delete', onclick: function () { destroy(row, this); } })
          ))
        ));
      });

      Admin.pager('plan-pagination', data.meta, state.page, function (next) { state.page = next; load(); });
    } catch (error) {
      Dx.mount('plan-body', Dx.h('tr', null, Dx.h('td', { colspan: '7' }, Dx.h('div', { class: 'banner banner-danger', text: error.message }))));
    }
  }

  function destroy(row, button) {
    Admin.confirmAction(
      'Delete bundle "' + row.name + '"? Passes already issued from it stay valid.',
      function () {
        return Dx.endpoints.plans.remove(row.id).then(function () {
          Dx.toast('Bundle deleted.', 'success');
          load();
        });
      },
      button,
      'Deleting…'
    );
  }

  function openForm(plan) {
    const editing = plan !== null;
    const featureRows = [];
    const list = Dx.h('div', { class: 'stack-tight', id: 'feature-list' });

    const featureInput = function (value) {
      const input = Dx.h('input', { class: 'input', type: 'text', name: 'feature', maxlength: '160', placeholder: 'What the buyer gets', value: value || '' });
      const row = Dx.h('div', { class: 'cluster' },
        input,
        Dx.h('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Remove feature' }, '×')
      );
      row.querySelector('button').addEventListener('click', function () {
        row.remove();
        featureRows.splice(featureRows.indexOf(input), 1);
      });
      featureRows.push(input);
      return row;
    };

    (editing ? plan.features : ['4K + HDR, 4 screens', 'No ads on YouTube Premium', 'Individual profile, your email only']).forEach(function (value) {
      list.appendChild(featureInput(value));
    });

    const modal = Dx.h('div', { class: 'modal hide', id: 'plan-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': editing ? 'Edit bundle' : 'New bundle' },
      Dx.h('div', { class: 'modal-box modal-box-wide' },
        Dx.h('div', { class: 'modal-head' },
          Dx.h('h2', { text: editing ? 'Edit ' + plan.name : 'New bundle' }),
          Dx.h('button', { class: 'icon-btn', type: 'button', 'data-close-modal': 'plan-modal', 'aria-label': 'Close' },
            Dx.h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'aria-hidden': 'true' },
              Dx.h('path', { d: 'M6 6l12 12M18 6 6 18' })))
        ),
        Dx.h('form', { id: 'plan-form' },
          Dx.h('div', { class: 'row-2' },
            Dx.h('label', { class: 'field' }, Dx.h('span', { class: 'label', text: 'Name' }),
              Dx.h('input', { class: 'input', name: 'name', maxlength: '120', value: editing ? plan.name : '', required: 'required' }),
              Dx.h('span', { class: 'error-text', 'data-for': 'name' })),
            Dx.h('label', { class: 'field' }, Dx.h('span', { class: 'label', text: 'Badge' }),
              Dx.h('input', { class: 'input', name: 'badge', maxlength: '40', placeholder: 'Most Popular', value: editing && plan.badge ? plan.badge : '' }),
              Dx.h('span', { class: 'hint', text: 'Optional ribbon text.' }),
              Dx.h('span', { class: 'error-text', 'data-for': 'badge' }))
          ),
          Dx.h('div', { class: 'row-2' },
            Dx.h('label', { class: 'field' }, Dx.h('span', { class: 'label', text: 'Price (৳)' }),
              Dx.h('input', { class: 'input', name: 'price', type: 'number', min: '1', step: '0.01', value: editing ? plan.price : '', required: 'required' }),
              Dx.h('span', { class: 'error-text', 'data-for': 'price' })),
            Dx.h('label', { class: 'field' }, Dx.h('span', { class: 'label', text: 'Duration (days)' }),
              Dx.h('input', { class: 'input', name: 'duration_days', type: 'number', min: '1', max: '3650', value: editing ? plan.duration_days : '30' }),
              Dx.h('span', { class: 'error-text', 'data-for': 'duration_days' }))
          ),
          Dx.h('label', { class: 'field' }, Dx.h('span', { class: 'label', text: 'Description' }),
            Dx.h('textarea', { class: 'textarea', name: 'description', maxlength: '2000' }, editing ? plan.description : ''),
            Dx.h('span', { class: 'error-text', 'data-for': 'description' })),
          Dx.h('div', { class: 'field' },
            Dx.h('span', { class: 'label', text: 'Feature list (max 12)' }),
            list,
            Dx.h('button', { class: 'btn btn-quiet btn-sm', type: 'button', id: 'add-feature' }, '+ Add line')
          ),
          Dx.h('div', { class: 'row-2' },
            Dx.h('label', { class: 'field' }, Dx.h('span', { class: 'label', text: 'Sort order' }),
              Dx.h('input', { class: 'input', name: 'sort_order', type: 'number', min: '0', max: '9999', value: editing ? plan.sort_order : '0' }),
              Dx.h('span', { class: 'hint', text: 'Lower shows first.' })),
            Dx.h('label', { class: 'switch' },
              Dx.h('input', { type: 'checkbox', name: 'status', checked: !editing || plan.status === 'active' ? 'checked' : null }),
              Dx.h('span', { class: 'track' }), Dx.h('span', { text: 'Visible on the Passes page' })
            )
          ),
          Dx.h('div', { class: 'modal-actions' },
            Dx.h('button', { class: 'btn btn-quiet', type: 'button', 'data-close-modal': 'plan-modal' }, 'Cancel'),
            Dx.h('button', { class: 'btn btn-primary', type: 'submit' }, editing ? 'Save changes' : 'Create bundle')
          )
        )
      )
    );

    document.body.appendChild(modal);
    Dx.openModal('plan-modal');

    document.getElementById('add-feature').addEventListener('click', function () {
      if (featureRows.length >= 12) { Dx.toast('Twelve lines is the limit.'); return; }
      list.appendChild(featureInput(''));
    });

    document.getElementById('plan-form').addEventListener('submit', function (event) {
      event.preventDefault();
      const submit = event.target.querySelector('button[type="submit"]');

      const features = featureRows
        .map(function (input) { return input.value.trim(); })
        .filter(function (text) { return text !== ''; })
        .slice(0, 12);

      const body = {
        name: this.name.value,
        badge: this.badge.value.trim() || null,
        price: Number(this.price.value),
        duration_days: Number(this.duration_days.value),
        description: this.description.value,
        features: features,
        sort_order: Number(this.sort_order.value || 0),
        status: this.status.checked ? 'active' : 'inactive',
      };

      Dx.setBusy(submit, true, 'Saving…');

      const request = editing ? Dx.endpoints.plans.update(plan.id, body) : Dx.endpoints.plans.create(body);

      request.then(function () {
        Dx.closeModal('plan-modal');
        modal.remove();
        Dx.toast(editing ? 'Bundle updated.' : 'Bundle created.', 'success');
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
