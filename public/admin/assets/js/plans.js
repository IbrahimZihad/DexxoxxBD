let allPlans = [];

function planRow(p) {
  return `
  <tr class="border-b border-line/60 last:border-0">
    <td class="p-4 font-medium">${p.name}</td>
    <td class="p-4 font-mono">${money(p.price)}</td>
    <td class="p-4 text-inksoft">${p.duration_days} days</td>
    <td class="p-4 text-inksoft">${p.badge || '—'}</td>
    <td class="p-4"><span class="stamp" style="color:${p.status === 'active' ? '#1F9C8B' : '#4A5068'}">${p.status}</span></td>
    <td class="p-4 text-right space-x-3 whitespace-nowrap">
      <button class="edit-btn text-navy font-semibold hover:underline" data-id="${p.id}">Edit</button>
      <button class="delete-btn text-danger font-semibold hover:underline" data-id="${p.id}">Delete</button>
    </td>
  </tr>`;
}

async function loadPlans() {
  const tbody = document.getElementById('plans-tbody');
  try {
    const { plans } = await AdminApi.plans.list();
    allPlans = plans;
    tbody.innerHTML = plans.length ? plans.map(planRow).join('') : `<tr><td class="p-4 text-inksoft" colspan="6">No plans yet.</td></tr>`;
    tbody.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', () => openModal(Number(b.dataset.id))));
    tbody.querySelectorAll('.delete-btn').forEach(b => b.addEventListener('click', () => deletePlan(Number(b.dataset.id))));
  } catch (e) {
    tbody.innerHTML = `<tr><td class="p-4 text-danger" colspan="6">${e.message}</td></tr>`;
  }
}

function openModal(id) {
  const form = document.getElementById('plan-form');
  form.reset();
  document.getElementById('pl_id').value = '';
  document.getElementById('modal-title').textContent = id ? 'Edit plan' : 'New plan';

  if (id) {
    const p = allPlans.find(x => x.id === id);
    document.getElementById('pl_id').value = p.id;
    document.getElementById('pl_name').value = p.name;
    document.getElementById('pl_description').value = p.description || '';
    document.getElementById('pl_price').value = p.price;
    document.getElementById('pl_duration_days').value = p.duration_days;
    document.getElementById('pl_features').value = (p.features || []).join('\n');
    document.getElementById('pl_badge').value = p.badge || '';
    document.getElementById('pl_status').value = p.status;
  }
  document.getElementById('plan-modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('plan-modal').classList.add('hidden'); }

async function deletePlan(id) {
  if (!confirm('Delete this plan? This cannot be undone.')) return;
  try { await AdminApi.plans.remove(id); adminToast('Plan deleted.', 'success'); loadPlans(); }
  catch (e) { adminToast(e.message, 'error'); }
}

(async function initPlansPage() {
  try { await requireAdminAuth(); } catch { return; }
  document.getElementById('admin-logout-btn').addEventListener('click', async () => { await AdminApi.logout(); location.href = 'index.html'; });
  document.getElementById('new-plan-btn').addEventListener('click', () => openModal(null));
  document.getElementById('cancel-plan-btn').addEventListener('click', closeModal);

  document.getElementById('plan-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('pl_id').value;
    const payload = {
      name: document.getElementById('pl_name').value.trim(),
      description: document.getElementById('pl_description').value.trim(),
      price: Number(document.getElementById('pl_price').value),
      duration_days: Number(document.getElementById('pl_duration_days').value),
      features: document.getElementById('pl_features').value.split('\n').map(s => s.trim()).filter(Boolean),
      badge: document.getElementById('pl_badge').value.trim() || null,
      status: document.getElementById('pl_status').value,
    };
    try {
      if (id) await AdminApi.plans.update(id, payload);
      else await AdminApi.plans.create(payload);
      adminToast('Plan saved.', 'success');
      closeModal();
      loadPlans();
    } catch (e) { adminToast(e.message, 'error'); }
  });

  loadPlans();
})();
