// ---- Toasts ----
function toast(message, type = 'info') {
  const wrap = document.getElementById('toast-wrap');
  if (!wrap) return alert(message);
  const colors = {
    info: 'bg-[var(--navy)] text-white',
    error: 'bg-[var(--danger)] text-white',
    success: 'bg-[var(--teal)] text-white',
  };
  const el = document.createElement('div');
  el.className = `${colors[type]} px-4 py-3 rounded-lg shadow-lg font-medium text-sm max-w-xs animate-[fadeIn_0.2s_ease-out]`;
  el.textContent = message;
  wrap.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }, 3200);
}

// ---- Navbar auth state + cart badge ----
async function initNav() {
  const authArea = document.getElementById('nav-auth-area');
  const cartBadge = document.getElementById('cart-badge');
  const adminLink = document.getElementById('nav-admin-link');

  try {
    const { user } = await Api.me();
    if (authArea) {
      authArea.innerHTML = `
        <a href="dashboard.html" class="text-sm font-medium hover:text-[var(--gold-ink)] transition">Hi, ${user.name.split(' ')[0]}</a>
        <button id="logout-btn" class="text-sm font-medium text-[var(--ink-soft)] hover:text-[var(--danger)] transition">Sign out</button>
      `;
      document.getElementById('logout-btn').addEventListener('click', async () => {
        await Api.logout();
        toast('Signed out.', 'success');
        setTimeout(() => location.href = 'index.html', 500);
      });
    }
    if (adminLink && user.role === 'admin') adminLink.classList.remove('hidden');
  } catch {
    if (authArea) {
      authArea.innerHTML = `
        <a href="login.html" class="text-sm font-medium hover:text-[var(--gold-ink)] transition">Sign in</a>
        <a href="register.html" class="text-sm font-semibold bg-[var(--navy)] text-white px-4 py-2 rounded-full hover:bg-[var(--ink)] transition">Get started</a>
      `;
    }
  }

  if (cartBadge) {
    try {
      const { items } = await Api.cart.list();
      const count = items.reduce((s, i) => s + i.quantity, 0);
      if (count > 0) { cartBadge.textContent = count; cartBadge.classList.remove('hidden'); }
    } catch { /* not logged in — ignore */ }
  }
}

function money(n) {
  return 'Tk ' + Number(n).toLocaleString('en-BD', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function fmtDate(d) {
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

document.addEventListener('DOMContentLoaded', initNav);
