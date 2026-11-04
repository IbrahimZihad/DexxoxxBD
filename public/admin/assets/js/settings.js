/* Site settings. Everything here is read by the storefront or the checkout, so
   the copy warns what each field actually touches before an admin changes it. */
(async function initSettings() {
  await Admin.start('Settings', 'Payment numbers, support contacts and storefront copy.', 'settings.html');

  const GROUPS = {
    general: {
      title: 'General',
      note: 'The tagline and announcement bar are storefront copy. The minimum order total is enforced when an order is created, and pass expiry runs whenever a passes list or the housekeeping sweep loads.',
    },
    payments: {
      title: 'Manual payment channels',
      note: 'Customers send money to these numbers. Checkout shows them exactly as typed, so verify against your own statement before saving. Card payments use the SSLCommerz credentials in the .env file, not this table.',
    },
    contact: {
      title: 'Support',
      note: 'Shown on the customer dashboard so a buyer knows who to reach when a payment needs checking.',
    },
  };

  let original = {};
  const fields = {};

  Admin.outlet(Dx.h('div', { class: 'stack' },
    Dx.h('div', { class: 'banner banner-warn' },
      Dx.h('span', { text: 'Secrets do not live here. Gateway API credentials, the app key and database passwords belong in the .env file next to the project, not in this table.' })
    ),
    Dx.h('form', { class: 'panel stack', id: 'settings-form', autocomplete: 'off' },
      Dx.h('div', { id: 'settings-groups', class: 'stack' }),
      Dx.h('div', { class: 'review-actions' },
        Dx.h('button', { class: 'btn btn-gold', type: 'submit', id: 'settings-save', disabled: true }, 'Save changes'),
        Dx.h('button', { class: 'btn btn-quiet', type: 'button', id: 'settings-reset', disabled: true }, 'Discard edits'),
        Dx.h('span', { class: 'faint', id: 'settings-dirty', text: 'Nothing changed yet.' })
      )
    )
  ));

  const form = document.getElementById('settings-form');
  const saveButton = document.getElementById('settings-save');
  const resetButton = document.getElementById('settings-reset');
  const dirtyNote = document.getElementById('settings-dirty');

  function norm(value) {
    if (value === true) return '1';
    if (value === false) return '0';
    return String(value === null || value === undefined ? '' : value);
  }

  function dirtyKeys() {
    return Object.keys(fields).filter(function (key) {
      return norm(fields[key].read()) !== norm(original[key]);
    });
  }

  function refreshDirty() {
    const changed = dirtyKeys();
    saveButton.disabled = changed.length === 0;
    resetButton.disabled = changed.length === 0;
    dirtyNote.textContent = changed.length === 0
      ? 'Nothing changed yet.'
      : changed.length + (changed.length === 1 ? ' setting differs from the saved value.' : ' settings differ from the saved value.');
  }

  function control(item) {
    const id = 'field-' + item.key;

    if (item.type === 'bool') {
      const input = Dx.h('input', { id: id, type: 'checkbox', name: item.key, checked: !!item.value });
      const state = Dx.h('span', { class: 'faint', text: item.value ? 'On' : 'Off' });
      input.addEventListener('change', function () { state.textContent = this.checked ? 'On' : 'Off'; refreshDirty(); });

      fields[item.key] = { read: function () { return input.checked ? '1' : '0'; }, focus: function () { input.focus(); } };

      return Dx.h('label', { class: 'field', for: id },
        Dx.h('span', { class: 'label', text: item.label }),
        Dx.h('span', { class: 'switch' }, input, Dx.h('span', { class: 'track' }), state),
        Dx.h('span', { class: 'hint mono', text: item.key })
      );
    }

    // The API flattens newlines and caps every string at 200 characters, so one
    // honest input per key beats a textarea that silently rewrites what you typed.
    const input = Dx.h('input', {
      class: 'input',
      id: id,
      name: item.key,
      type: item.type === 'number' ? 'number' : 'text',
      inputmode: item.type === 'number' ? 'decimal' : null,
      value: norm(item.value),
      maxlength: '200',
    });
    input.addEventListener('input', refreshDirty);

    fields[item.key] = { read: function () { return input.value; }, focus: function () { input.focus(); } };

    return Dx.h('label', { class: 'field', for: id },
      Dx.h('span', { class: 'label' },
        item.label,
        item.is_public
          ? Dx.h('span', { class: 'pill', text: 'customer-facing' })
          : Dx.h('span', { class: 'faint', text: ' · admin only' })
      ),
      input,
      Dx.h('span', { class: 'hint mono', text: item.key })
    );
  }

  function render(groups) {
    const order = Object.keys(groups).sort(function (a, b) {
      const pa = Object.keys(GROUPS).indexOf(a);
      const pb = Object.keys(GROUPS).indexOf(b);
      return (pa < 0 ? 99 : pa) - (pb < 0 ? 99 : pb);
    });

    Dx.mount('settings-groups', order.map(function (name) {
      const copy = GROUPS[name] || { title: name, note: '' };
      return Dx.h('section', { class: 'settings-group' },
        Dx.h('h3', { text: copy.title }),
        copy.note ? Dx.h('p', { class: 'muted', text: copy.note }) : null,
        Dx.h('div', { class: 'row-2' }, groups[name].map(control))
      );
    }));
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    const changed = {};
    dirtyKeys().forEach(function (key) { changed[key] = fields[key].read(); });

    Dx.setBusy(saveButton, true, 'Saving…');

    Dx.endpoints.settings.save(changed)
      .then(function (result) {
        original = result.settings;
        Dx.setBusy(saveButton, false);
        Dx.toast(result.saved + (result.saved === 1 ? ' setting saved.' : ' settings saved.'), 'success');
        load();
      })
      .catch(function (error) {
        Dx.setBusy(saveButton, false);
        Dx.toast(error.message, 'error');
      });
  });

  resetButton.addEventListener('click', function () { load(); });

  async function load() {
    const data = await Dx.endpoints.settings.admin();
    original = data.settings;
    Object.keys(fields).forEach(function (key) { delete fields[key]; });
    render(data.groups);
    refreshDirty();
  }

  load().catch(function (error) {
    Admin.outlet(Dx.h('div', { class: 'banner banner-danger', text: error.message }));
  });
})();
