/* Admin accounts: role and status only — passwords are never readable here. */
(async function initAdminUsers() {
  const me = await Admin.start('Accounts', 'Everyone with a login, customers and staff.', 'users.html');

  let state = { q: '', page: 1 };

  Admin.outlet(Dx.h('div', { class: 'panel stack' },
    Dx.h('div', { class: 'filters' },
      Dx.h('input', { class: 'input', id: 'user-search', type: 'search', placeholder: 'Name or email…', autocomplete: 'off' }),
      Dx.h('span', { class: 'hint', text: 'Suspended accounts cannot sign in; their existing passes stay listed for support.' })
    ),
    Dx.h('div', { class: 'table-wrap' }, Dx.h('table', { class: 'table' },
      Dx.h('thead', null, Dx.h('tr', null,
        ['Account', 'Phone', 'Role', 'Status', 'Last seen', 'Joined', ''].map(function (label) {
          return Dx.h('th', { scope: 'col', text: label });
        })
      )),
      Dx.h('tbody', { id: 'user-body' })
    )),
    Dx.h('div', { class: 'pagination', id: 'user-pagination' })
  ));

  const search = document.getElementById('user-search');
  let debounce = null;
  search.addEventListener('input', function () {
    clearTimeout(debounce);
    debounce = setTimeout(function () { state.q = search.value.trim(); state.page = 1; load(); }, 320);
  });

  function save(row, body, button, message) {
    Dx.setBusy(button, true, 'Saving…');
    Dx.endpoints.admin.setUser(row.id, body).then(function () {
      Dx.setBusy(button, false);
      Dx.toast(message, 'success');
      load();
    }).catch(function (error) {
      Dx.setBusy(button, false);
      Dx.toast(error.message, 'error');
    });
  }

  async function load() {
    Dx.skeletonRows('user-body', 7, 5);

    try {
      const data = await Dx.endpoints.admin.users('?page=' + state.page + '&per_page=25&q=' + encodeURIComponent(state.q));
      const body = document.getElementById('user-body');
      Dx.clear(body);

      if (!data.users.length) {
        body.appendChild(Dx.h('tr', null, Dx.h('td', { colspan: '7' },
          Dx.h('div', { class: 'empty', text: 'No account matches that search.' }))));
        Dx.clear('user-pagination');
        return;
      }

      data.users.forEach(function (row) {
        const isSelf = Number(row.id) === Number(me.id);

        const roleSelect = Dx.h('select', { class: 'select' },
          ['customer', 'admin'].map(function (role) {
            return Dx.h('option', { value: role, text: role, selected: row.role === role ? 'selected' : null });
          })
        );

        if (isSelf) roleSelect.setAttribute('disabled', 'disabled');

        roleSelect.addEventListener('change', function () {
          save(row, { role: roleSelect.value }, roleSelect, row.name + ' is now ' + roleSelect.value + '.');
        });

        const statusButton = Dx.h('button', {
          class: 'btn btn-sm ' + (row.status === 'active' ? 'btn-danger' : 'btn-quiet'),
          type: 'button',
          text: row.status === 'active' ? 'Suspend' : 'Re-activate',
        });
        statusButton.addEventListener('click', function () {
          const next = row.status === 'active' ? 'suspended' : 'active';
          Admin.confirmAction(
            'Set ' + row.email + ' to ' + next + '?',
            function () {
              return Dx.endpoints.admin.setUser(row.id, { status: next }).then(function () {
                Dx.toast(row.name + ' is ' + next + '.', 'success');
                load();
              });
            },
            statusButton,
            'Saving…'
          );
        });
        if (isSelf) statusButton.setAttribute('disabled', 'disabled');

        body.appendChild(Dx.h('tr', null,
          Dx.h('td', null, Dx.h('div', { class: 'cell-title' },
            Dx.h('span', { class: 'avatar' + (row.role === 'admin' ? ' avatar-gold' : ''), text: Dx.initials(row.name) }),
            Dx.h('span', null,
              Dx.h('span', { text: row.name + (isSelf ? ' (you)' : '') }),
              Dx.h('span', { class: 'sub', text: row.email })
            )
          )),
          Dx.h('td', { class: 'mono', text: row.phone || '—' }),
          Dx.h('td', null, roleSelect),
          Dx.h('td', null, Admin.stamp(row.status)),
          Dx.h('td', { text: row.last_login_at ? Dx.formatDateTime(row.last_login_at) : 'Never' }),
          Dx.h('td', { text: Dx.formatDate(row.created_at) }),
          Dx.h('td', null, Dx.h('div', { class: 'actions' }, statusButton))
        ));
      });

      Admin.pager('user-pagination', data.meta, state.page, function (next) { state.page = next; load(); });
    } catch (error) {
      Dx.mount('user-body', Dx.h('tr', null, Dx.h('td', { colspan: '7' }, Dx.h('div', { class: 'banner banner-danger', text: error.message }))));
    }
  }

  load();
})();
