let allProducts = [];

function productRow(p) {
  return `
  <tr class="border-b border-line/60 last:border-0">
    <td class="p-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-line overflow-hidden shrink-0">
          ${p.image_url ? `<img src="${p.image_url}" class="w-full h-full object-cover">` : ''}
        </div>
        <span class="font-medium">${p.name}</span>
      </div>
    </td>
    <td class="p-4 text-inksoft">${p.category_name || '—'}</td>
    <td class="p-4 font-mono">${money(p.price)}</td>
    <td class="p-4 text-inksoft">${p.duration_days} days</td>
    <td class="p-4"><span class="stamp" style="color:${p.status === 'active' ? '#1F9C8B' : '#4A5068'}">${p.status}</span></td>
    <td class="p-4 text-right space-x-3 whitespace-nowrap">
      <button class="edit-btn text-navy font-semibold hover:underline" data-id="${p.id}">Edit</button>
      <button class="delete-btn text-danger font-semibold hover:underline" data-id="${p.id}">Delete</button>
    </td>
  </tr>`;
}

async function loadProducts() {
  const tbody = document.getElementById('products-tbody');
  try {
    const { products } = await AdminApi.products.list();
    allProducts = products;
    tbody.innerHTML = products.length ? products.map(productRow).join('') : `<tr><td class="p-4 text-inksoft" colspan="6">No products yet.</td></tr>`;

    tbody.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', () => openModal(Number(b.dataset.id))));
    tbody.querySelectorAll('.delete-btn').forEach(b => b.addEventListener('click', () => deleteProduct(Number(b.dataset.id))));
  } catch (e) {
    tbody.innerHTML = `<tr><td class="p-4 text-danger" colspan="6">${e.message}</td></tr>`;
  }
}

function openModal(id) {
  const modal = document.getElementById('product-modal');
  const form = document.getElementById('product-form');
  form.reset();
  document.getElementById('p_id').value = '';
  document.getElementById('modal-title').textContent = id ? 'Edit product' : 'New product';

  if (id) {
    const p = allProducts.find(x => x.id === id);
    document.getElementById('p_id').value = p.id;
    document.getElementById('p_name').value = p.name;
    document.getElementById('p_description').value = p.description || '';
    document.getElementById('p_image_url').value = p.image_url || '';
    document.getElementById('p_price').value = p.price;
    document.getElementById('p_compare_at_price').value = p.compare_at_price || '';
    document.getElementById('p_duration_days').value = p.duration_days;
    document.getElementById('p_stock').value = p.stock;
    document.getElementById('p_category_id').value = p.category_id || 1;
    document.getElementById('p_status').value = p.status;
    document.getElementById('p_is_featured').checked = !!Number(p.is_featured);
  }
  modal.classList.remove('hidden');
}

function closeModal() { document.getElementById('product-modal').classList.add('hidden'); }

async function deleteProduct(id) {
  if (!confirm('Delete this product? This cannot be undone.')) return;
  try {
    await AdminApi.products.remove(id);
    adminToast('Product deleted.', 'success');
    loadProducts();
  } catch (e) { adminToast(e.message, 'error'); }
}

(async function initProductsPage() {
  try { await requireAdminAuth(); } catch { return; }

  document.getElementById('admin-logout-btn').addEventListener('click', async () => { await AdminApi.logout(); location.href = 'index.html'; });
  document.getElementById('new-product-btn').addEventListener('click', () => openModal(null));
  document.getElementById('cancel-product-btn').addEventListener('click', closeModal);

  document.getElementById('product-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('p_id').value;
    const payload = {
      name: document.getElementById('p_name').value.trim(),
      description: document.getElementById('p_description').value.trim(),
      image_url: document.getElementById('p_image_url').value.trim() || null,
      price: Number(document.getElementById('p_price').value),
      compare_at_price: document.getElementById('p_compare_at_price').value ? Number(document.getElementById('p_compare_at_price').value) : null,
      duration_days: Number(document.getElementById('p_duration_days').value),
      stock: Number(document.getElementById('p_stock').value),
      category_id: Number(document.getElementById('p_category_id').value),
      status: document.getElementById('p_status').value,
      is_featured: document.getElementById('p_is_featured').checked,
    };
    try {
      if (id) await AdminApi.products.update(id, payload);
      else await AdminApi.products.create(payload);
      adminToast('Product saved.', 'success');
      closeModal();
      loadProducts();
    } catch (e) { adminToast(e.message, 'error'); }
  });

  loadProducts();
})();
