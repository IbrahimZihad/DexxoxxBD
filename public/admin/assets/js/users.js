function userRow(u) {
  return `
  <tr class="border-b border-line/60 last:border-0">
    <td class="p-4 font-medium">${u.name}</td>
    <td class="p-4 text-inksoft">${u.email}</td>
    <td class="p-4">
      <select class="role-select border border-line rounded-lg px-2 py-1 text-xs" data-id="${u.id}">
        <option value="customer" ${u.role === 'customer' ? 'selected' : ''}>Customer</option>
        <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Admin</option>
      </select>
    </td>
    <td class="p-4">
      <span class="stamp" style="color:${u.status === 'active' ? '#1F9C8B' : '#D64545'}">${u.status}</span>
    </td>
    <td class="p-4 text-inksoft">${fmtDate(u.created_at)}</td>
    <td class="p-4 text-right">
      <button class="toggle-status-btn text-sm font-semibold ${u.status === 'active' ? 'text-danger' : 'text-teal'} hover:underline" data-id="${u.id}" data-next="${u.status === 'active' ? 'suspended' : 'active'}">
        ${u.status === 'active' ? 'Suspend' : 'Reactivate'}
      </button>
    </td>
  </tr>`;
}

async function loadUsers() {
  const tbody = document.getElementById('users-tbody');
  try {
    const { users } = await AdminApi.users.list();
    tbody.innerHTML = users.map(userRow).join('');

    tbody.querySelectorAll('.role-select').forEach(sel => sel.addEventListener('change', async () => {
      try { await AdminApi.users.update(sel.dataset.id, { role: sel.value }); adminToast('Role updated.', 'success'); }
      catch (e) { adminToast(e.message, 'error'); loadUsers(); }
    }));

    tbody.querySelectorAll('.toggle-status-btn').forEach(btn => btn.addEventListener('click', async () => {
      try { await AdminApi.users.update(btn.dataset.id, { status: btn.dataset.next }); adminToast('Status updated.', 'success'); loadUsers(); }
      catch (e) { adminToast(e.message, 'error'); }
    }));
  } catch (e) {
    tbody.innerHTML = `<tr><td class="p-4 text-danger" colspan="6">${e.message}</td></tr>`;
  }
}

(async function initUsersPage() {
  try { await requireAdminAuth(); } catch { return; }
  document.getElementById('admin-logout-btn').addEventListener('click', async () => { await AdminApi.logout(); location.href = 'index.html'; });
  loadUsers();
})();
