/* Login and register share one script: the page decides which form exists. */
(function initAuth() {
  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');

  // On the admin panel, relative links hang off admin/.
  const inAdmin = /\/admin\/[^/]*$/.test(location.pathname);
  const prefixes = { customer: inAdmin ? '../' : '', admin: inAdmin ? '' : 'admin/' };

  // Already signed in? Do not make the buyer type a password again.
  Dx.currentUser().then(function (user) {
    if (!user) return;
    location.href = landing(user);
  });

  function landing(user) {
    const target = 'dashboard.html';
    const next = Dx.queryParam('next');

    if (next) {
      const safe = Dx.safeRedirect(next, '');
      // Reject anything that would bounce the user straight back to a login page.
      if (safe && !/login|register|index\.html/.test(safe)) return safe;
    }

    return prefixes[user.role === 'admin' ? 'admin' : 'customer'] + target;
  }

  function fail(panelId, message) {
    const panel = document.getElementById(panelId);
    if (!panel) { Dx.toast(message, 'error'); return; }
    panel.classList.remove('hide');
    panel.textContent = message;
  }

  /* ---------------- login ---------------- */
  if (loginForm) {
    loginForm.addEventListener('submit', function (event) {
      event.preventDefault();
      const button = this.querySelector('button[type="submit"]');
      Dx.setBusy(button, true, 'Checking…');

      Dx.endpoints.auth.login({
        email: this.email.value.trim(),
        password: this.password.value,
      }).then(function (result) {
        Dx.toast('Signed in as ' + result.user.name, 'success');
        location.href = landing(result.user);
      }).catch(function (error) {
        Dx.setBusy(button, false);
        Dx.fieldErrors(error);
        fail('login-error', error.message);
      });
    });
  }

  /* ---------------- register ---------------- */
  if (registerForm) {
    const meter = document.getElementById('password-meter');
    const hint = document.getElementById('password-hint');
    const password = document.getElementById('register-password');

    const levels = [
      ['Use at least 8 characters.', 0, ''],
      ['Too short — 8 characters minimum.', 20, 'is-low'],
      ['Okay, but predictable.', 55, ''],
      ['Strong enough.', 100, 'is-strong'],
    ];

    password.addEventListener('input', function () {
      const value = password.value;
      let score = 0;
      if (value.length >= 8) score++;
      if (/[0-9]/.test(value) && /[A-Za-z]/.test(value)) score++;
      if (/[^A-Za-z0-9]/.test(value) || value.length >= 14) score++;

      const level = levels[score];
      meter.classList.remove('is-low', 'is-strong');
      if (level[2]) meter.classList.add(level[2]);
      meter.style.setProperty('--w', level[1] + '%');
      hint.textContent = level[0];
    });

    registerForm.addEventListener('submit', function (event) {
      event.preventDefault();
      const button = this.querySelector('button[type="submit"]');

      if (this.password.value !== this.password_confirm.value) {
        Dx.fieldErrors({ fields: { password_confirm: 'Both passwords must match.' } });
        fail('register-error', 'The two passwords do not match.');
        return;
      }

      if (!this.terms.checked) {
        fail('register-error', 'Please accept the resale terms to continue.');
        return;
      }

      Dx.setBusy(button, true, 'Creating…');

      Dx.endpoints.auth.register({
        name: this.name.value.trim(),
        email: this.email.value.trim(),
        phone: this.phone.value.trim() || null,
        password: this.password.value,
      }).then(function (result) {
        Dx.toast('Account ready — welcome, ' + result.user.name, 'success');
        location.href = landing(result.user);
      }).catch(function (error) {
        Dx.setBusy(button, false);
        Dx.fieldErrors(error);
        fail('register-error', error.message);
      });
    });
  }
})();
