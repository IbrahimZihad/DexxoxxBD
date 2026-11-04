/* Manual payment queue: check the reference against your own wallet statement
   before approving. Approval is what issues the customer's passes. */
(async function initManualPayments() {
  await Admin.start('Payment reviews', 'bKash, Nagad, Rocket and bank transfers awaiting a decision.', 'manual-payments.html');

  let state = { status: Dx.queryParam('status') || 'pending', page: 1 };

  Admin.outlet(Dx.h('div', { class: 'panel stack' },
    Dx.h('div', { class: 'filters', id: 'review-tabs' }),
    Dx.h('div', { class: 'table-wrap' }, Dx.h('table', { class: 'table' },
      Dx.h('thead', null, Dx.h('tr', null,
        ['Reference', 'Order', 'Channel', 'Amount', 'Sender', 'Buyer', 'Filed', 'Status', ''].map(function (label) {
          return Dx.h('th', { scope: 'col', text: label });
        })
      )),
      Dx.h('tbody', { id: 'review-body' })
    )),
    Dx.h('div', { class: 'pagination', id: 'review-pagination' })
  ));

  function tabs(counts) {
    const options = [['pending', 'Waiting'], ['approved', 'Approved'], ['rejected', 'Rejected'], ['', 'All']];

    Dx.mount('review-tabs', options.map(function (option) {
      const count = option[0] === '' ? (counts.pending + counts.approved + counts.rejected) : counts[option[0]];
      const chip = Dx.h('button', { class: 'chip', type: 'button', text: option[1] + ' · ' + count });
      if (state.status === option[0]) chip.classList.add('is-active');
      chip.addEventListener('click', function () {
        state.status = option[0];
        state.page = 1;
        load();
      });
      return chip;
    }));
  }

  function decide(row, action, button) {
    const modal = Dx.h('div', { class: 'modal hide', id: 'decision-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Record decision' },
      Dx.h('div', { class: 'modal-box' },
        Dx.h('div', { class: 'modal-head' },
          Dx.h('h2', { text: action === 'approve' ? 'Approve payment' : 'Reject payment' }),
          Dx.h('button', { class: 'icon-btn', type: 'button', 'data-close-modal': 'decision-modal', 'aria-label': 'Close' },
            Dx.h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'aria-hidden': 'true' },
              Dx.h('path', { d: 'M6 6l12 12M18 6 6 18' })))
        ),
        Dx.h('p', { class: 'muted', text: action === 'approve'
          ? 'Order ' + row.order_number + ' becomes paid and ' + Dx.money(row.total_amount) + ' worth of passes are issued to ' + row.user_name + '.'
          : 'Order ' + row.order_number + ' is closed as failed, reserved stock returns, and ' + row.user_name + ' sees your note.' }),
        Dx.h('form', { id: 'decision-form' },
          Dx.h('label', { class: 'field' },
            Dx.h('span', { class: 'label', text: 'Note for the customer' }),
            Dx.h('textarea', { class: 'textarea', name: 'admin_note', maxlength: '500', placeholder: action === 'approve' ? 'Reference matched my statement.' : 'No such transaction reached my account.' })
          ),
          Dx.h('div', { class: 'modal-actions' },
            Dx.h('button', { class: 'btn btn-quiet', type: 'button', 'data-close-modal': 'decision-modal' }, 'Cancel'),
            Dx.h('button', { class: 'btn ' + (action === 'approve' ? 'btn-gold' : 'btn-danger'), type: 'submit' }, action === 'approve' ? 'Approve and issue' : 'Reject')
          )
        )
      )
    );

    document.body.appendChild(modal);
    Dx.openModal('decision-modal');

    document.getElementById('decision-form').addEventListener('submit', function (event) {
      event.preventDefault();
      const submit = event.target.querySelector('button[type="submit"]');
      Dx.setBusy(submit, true, 'Saving…');

      Dx.endpoints.admin.reviewPayment(row.id, { action: action, admin_note: this.admin_note.value })
        .then(function (result) {
          Dx.closeModal('decision-modal');
          modal.remove();
          Dx.setBusy(button, false);
          Dx.toast(result.message, 'success');
          load();
        })
        .catch(function (error) {
          Dx.setBusy(submit, false);
          Dx.toast(error.message, 'error');
        });
    });
  }

  async function load() {
    Dx.skeletonRows('review-body', 9, 4);

    try {
      const data = await Dx.endpoints.admin.manualPayments('?page=' + state.page + '&status=' + encodeURIComponent(state.status));
      tabs(data.counts);

      const body = document.getElementById('review-body');
      Dx.clear(body);

      if (!data.payments.length) {
        body.appendChild(Dx.h('tr', null, Dx.h('td', { colspan: '9' },
          Dx.h('div', { class: 'empty', text: state.status === 'pending' ? 'Queue is clear — nothing waiting for a decision.' : 'Nothing in this bucket.' }))));
        Dx.clear('review-pagination');
        return;
      }

      data.payments.forEach(function (row) {
        const actions = row.status === 'pending'
          ? Dx.h('div', { class: 'review-actions' },
            Dx.h('button', { class: 'btn btn-gold btn-sm', type: 'button', text: 'Approve' , onclick: function () { decide(row, 'approve', this); } }),
            Dx.h('button', { class: 'btn btn-danger btn-sm', type: 'button', text: 'Reject', onclick: function () { decide(row, 'reject', this); } })
          )
          : Dx.h('span', { class: 'faint', text: (row.reviewed_by_name ? 'by ' + row.reviewed_by_name : 'decided') + ' · ' + Dx.formatDateTime(row.reviewed_at) });

        body.appendChild(Dx.h('tr', null,
          Dx.h('td', null, Dx.h('code', { class: 'pass-code', text: row.transaction_id })),
          Dx.h('td', { class: 'mono', text: row.order_number }),
          Dx.h('td', { text: row.payment_method }),
          Dx.h('td', { class: 'num', text: Dx.money(row.amount) }),
          Dx.h('td', { class: 'mono', text: row.sender_number || '—' }),
          Dx.h('td', null, Dx.h('div', { class: 'cell-title', text: row.user_name }), Dx.h('span', { class: 'sub', text: row.user_email })),
          Dx.h('td', { text: Dx.formatDateTime(row.created_at) }),
          Dx.h('td', null, Admin.stamp(row.status)),
          Dx.h('td', null, actions)
        ));
      });

      Admin.pager('review-pagination', data.meta, state.page, function (next) { state.page = next; load(); });
      Admin.refreshReviewBadge();
    } catch (error) {
      Dx.mount('review-body', Dx.h('tr', null, Dx.h('td', { colspan: '9' }, Dx.h('div', { class: 'banner banner-danger', text: error.message }))));
    }
  }

  load();
})();
