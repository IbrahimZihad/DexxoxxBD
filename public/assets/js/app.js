/* =============================================================================
   ZapSub — public/assets/js/app.js
   Vanilla ES2020, single file, loaded with `defer`, zero dependencies.
   CSP is script-src 'self' 'nonce-...' : no inline handlers, no inline scripts,
   no eval, no innerHTML for anything we build ourselves. Everything is driven
   by data attributes and every module is a no-op when its markup is absent.
   Progressive enhancement: forms stay natively submittable (JS never removes
   the default behaviour except on the fetch path it actually handled).
   ========================================================================== */

(() => {
  'use strict';

  const doc = document;
  const win = window;
  const REDUCED_MOTION =
    typeof win.matchMedia === 'function' &&
    win.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /** All matches as a real array (safe on NodeList across engines). */
  const findAll = (selector, root) =>
    Array.prototype.slice.call((root || doc).querySelectorAll(selector));

  /** Closest ancestor carrying an attribute (Element.closest may be missing). */
  const closestAttr = (node, attrName) => {
    let current = node;
    while (current && current !== doc.body) {
      if (current.hasAttribute && current.hasAttribute(attrName)) return current;
      current = current.parentElement;
    }
    return null;
  };

  const toNumber = (value, fallback) => {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  };

  /** Thousands-separated integer/string, e.g. 12500 -> "12,500". */
  const withSeparators = (value) => {
    const asText = String(value);
    if (asText.indexOf('.') !== -1) return asText;
    return asText.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  };

  /* --------------------------------------------------------------------------
     Toasts — host is created on demand so views need no extra markup.
     ----------------------------------------------------------------------- */
  const TOAST_CLASSES = [
    'pointer-events-auto',
    'animate-pop',
    'max-w-md',
    'w-full',
    'rounded-brand',
    'px-4',
    'py-3',
    'text-sm',
    'font-semibold',
    'text-white',
    'shadow-card',
    'cursor-pointer',
    'select-none',
  ];

  const toastHost = () => {
    let host = doc.querySelector('[data-toast-host]');
    if (!host) {
      host = doc.createElement('div');
      host.setAttribute('data-toast-host', '');
      host.setAttribute('aria-live', 'polite');
      host.className =
        'fixed inset-x-0 top-4 z-[9999] flex flex-col items-center gap-2 px-4 pointer-events-none';
      doc.body.appendChild(host);
    }
    return host;
  };

  const toast = (message, tone) => {
    if (!doc.body) return null;
    const el = doc.createElement('div');
    el.setAttribute('role', 'status');
    el.className = TOAST_CLASSES.concat(tone === 'fail' ? 'bg-rose-600' : 'bg-emerald-600').join(
      ' '
    );
    el.textContent = message === undefined || message === null ? '' : String(message);

    let removed = false;
    let timer = 0;
    const dismiss = () => {
      if (removed) return;
      removed = true;
      if (timer) win.clearTimeout(timer);
      if (el.parentNode) el.parentNode.removeChild(el);
    };
    timer = win.setTimeout(dismiss, 4200);

    el.addEventListener('click', dismiss);
    toastHost().appendChild(el);
    return el;
  };

  /* --------------------------------------------------------------------------
     [data-reveal] — one-shot scroll reveal at ~12% visibility.
     ----------------------------------------------------------------------- */
  const initReveal = () => {
    const items = findAll('[data-reveal]');
    if (!items.length) return;

    if (typeof win.IntersectionObserver !== 'function' || REDUCED_MOTION) {
      items.forEach((el) => el.classList.add('is-revealed'));
      return;
    }

    const observer = new win.IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-revealed');
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );

    items.forEach((el) => observer.observe(el));
  };

  /* --------------------------------------------------------------------------
     [data-counter] — eased count-up with thousands separators.
     ----------------------------------------------------------------------- */
  const runCounter = (el) => {
    const target = toNumber(el.getAttribute('data-counter-to'), 0);
    const prefix = el.getAttribute('data-counter-prefix') || '';
    const suffix = el.getAttribute('data-counter-suffix') || '';
    const decimals = target % 1 === 0 ? 0 : 2;
    const write = (value) => {
      const rounded = decimals ? value.toFixed(decimals) : String(Math.round(value));
      el.textContent = prefix + withSeparators(rounded) + suffix;
    };

    if (REDUCED_MOTION) {
      write(target);
      return;
    }

    const duration = 1200;
    const start =
      typeof win.performance === 'object' && typeof win.performance.now === 'function'
        ? win.performance.now()
        : Date.now();

    const step = (now) => {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
      write(target * eased);
      if (progress < 1) win.requestAnimationFrame(step);
      else write(target);
    };

    if (typeof win.requestAnimationFrame !== 'function') {
      win.setTimeout(() => write(target), 0);
      return;
    }
    win.requestAnimationFrame(step);
  };

  const initCounters = () => {
    const items = findAll('[data-counter]');
    if (!items.length) return;

    if (typeof win.IntersectionObserver !== 'function') {
      items.forEach(runCounter);
      return;
    }

    const observer = new win.IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          runCounter(entry.target);
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0.12 }
    );

    items.forEach((el) => observer.observe(el));
  };

  /* --------------------------------------------------------------------------
     [data-tabs] — button/panel pairs keyed by data value.
     ----------------------------------------------------------------------- */
  const applyTabs = (container, key) => {
    findAll('[data-tab-btn]', container).forEach((btn) => {
      const on = btn.getAttribute('data-tab-btn') === key;
      btn.classList.toggle('is-active', on);
      btn.classList.toggle('is-inactive', !on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    findAll('[data-tab-panel]', container).forEach((panel) => {
      const on = panel.getAttribute('data-tab-panel') === key;
      if (on) panel.removeAttribute('hidden');
      else panel.setAttribute('hidden', '');
    });
  };

  const initTabs = () => {
    findAll('[data-tabs]').forEach((container) => {
      const buttons = findAll('[data-tab-btn]', container);
      const panels = findAll('[data-tab-panel]', container);
      if (!buttons.length || !panels.length) return;

      // Adopt the state the markup shipped with (do not fight the view).
      const activeBtn = buttons.filter(
        (btn) => btn.classList.contains('is-active') && !btn.classList.contains('is-inactive')
      )[0];
      const visiblePanel = panels.filter((panel) => !panel.hasAttribute('hidden'))[0];
      const startKey =
        (activeBtn && activeBtn.getAttribute('data-tab-btn')) ||
        (visiblePanel && visiblePanel.getAttribute('data-tab-panel'));
      if (startKey) applyTabs(container, startKey);

      buttons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          applyTabs(container, btn.getAttribute('data-tab-btn'));
        });
      });
    });
  };

  /* --------------------------------------------------------------------------
     [data-accordion] — one open panel per group.
     ----------------------------------------------------------------------- */
  const closeAccordionSiblings = (group, index) => {
    findAll('[data-accordion-panel]', group).forEach((panel, position) => {
      if (position === index) return;
      panel.setAttribute('hidden', '');
    });
    findAll('[data-accordion-btn]', group).forEach((btn, position) => {
      if (position === index) return;
      btn.setAttribute('aria-expanded', 'false');
      btn.classList.remove('is-active');
    });
  };

  const initAccordion = () => {
    const groups = findAll('[data-accordion]');
    groups.forEach((group) => {
      const buttons = findAll('[data-accordion-btn]', group);
      buttons.forEach((btn, index) => {
        if (!btn.hasAttribute('aria-expanded')) btn.setAttribute('aria-expanded', 'false');
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          const panels = findAll('[data-accordion-panel]', group);
          const panel = panels[index];
          if (!panel) return;
          const willOpen = panel.hasAttribute('hidden');
          if (willOpen) {
            panel.removeAttribute('hidden');
            btn.setAttribute('aria-expanded', 'true');
            btn.classList.add('is-active');
            closeAccordionSiblings(group, index);
          } else {
            panel.setAttribute('hidden', '');
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('is-active');
          }
        });
      });
    });
  };

  /* --------------------------------------------------------------------------
     [data-qty] — clamped stepper keeping the input in sync.
     ----------------------------------------------------------------------- */
  const clampQty = (group, rawValue) => {
    const min = toNumber(group.getAttribute('data-min'), 1);
    const max = toNumber(group.getAttribute('data-max'), 999);
    const value = Math.round(toNumber(rawValue, min));
    return Math.min(Math.max(value, min), max);
  };

  const initQty = () => {
    findAll('[data-qty]').forEach((group) => {
      const input = group.querySelector('[data-qty-input]');
      const minus = group.querySelector('[data-qty-minus]');
      const plus = group.querySelector('[data-qty-plus]');
      if (!input) return;

      let syncing = false; // stops our own change event from re-entering write()
      const read = () => clampQty(group, input.value);
      const write = (value) => {
        input.value = String(value);
        if (syncing) return;
        syncing = true;
        input.dispatchEvent(new win.Event('change', { bubbles: true }));
        syncing = false;
      };

      if (minus) {
        minus.addEventListener('click', (event) => {
          event.preventDefault();
          write(clampQty(group, read() - 1));
        });
      }
      if (plus) {
        plus.addEventListener('click', (event) => {
          event.preventDefault();
          write(clampQty(group, read() + 1));
        });
      }
      input.addEventListener('change', () => {
        if (syncing) return;
        write(read());
      });
      input.addEventListener('blur', () => {
        if (syncing) return;
        write(read());
      });

      // Normalise whatever the server pre-filled.
      const initial = read();
      if (String(initial) !== String(input.value).trim()) input.value = String(initial);
    });
  };

  /* --------------------------------------------------------------------------
     [data-header] — condensed after ~12px of scroll.
     ----------------------------------------------------------------------- */
  const initHeader = () => {
    const headers = findAll('[data-header]');
    if (!headers.length) return;

    let ticking = false;
    const update = () => {
      const scrolled = (win.pageYOffset || doc.documentElement.scrollTop || 0) > 12;
      headers.forEach((header) => header.classList.toggle('is-scrolled', scrolled));
      ticking = false;
    };
    const onScroll = () => {
      if (ticking) return;
      ticking = true;
      if (typeof win.requestAnimationFrame === 'function') win.requestAnimationFrame(update);
      else update();
    };

    win.addEventListener('scroll', onScroll, { passive: true });
    update();
  };

  /* --------------------------------------------------------------------------
     [data-mobile-nav] — hamburger + panel.
     ----------------------------------------------------------------------- */
  const initMobileNav = () => {
    findAll('[data-mobile-nav-btn]').forEach((btn) => {
      const scope = closestAttr(btn, 'data-mobile-nav') || btn;
      const findPanel = () => {
        if (scope.hasAttribute && scope.hasAttribute('data-mobile-nav-panel')) return scope;
        const inner = scope.querySelector ? scope.querySelector('[data-mobile-nav-panel]') : null;
        return inner || doc.querySelector('[data-mobile-nav-panel]');
      };

      const setOpen = (open) => {
        const panel = findPanel();
        if (!panel) return;
        if (open) panel.removeAttribute('hidden');
        else panel.setAttribute('hidden', '');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      };

      if (!btn.hasAttribute('aria-expanded')) btn.setAttribute('aria-expanded', 'false');
      if (!btn.hasAttribute('type')) btn.setAttribute('type', 'button');

      btn.addEventListener('click', (event) => {
        event.preventDefault();
        const panel = findPanel();
        if (!panel) return;
        setOpen(panel.hasAttribute('hidden'));
      });

      // Close after picking a destination, and on Escape.
      const panel = findPanel();
      if (panel) {
        findAll('a[href]', panel).forEach((link) => {
          link.addEventListener('click', () => setOpen(false));
        });
      }
      doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
      });
    });
  };

  /* --------------------------------------------------------------------------
     AJAX forms — <form data-ajax> posted with fetch, JSON in / JSON out.
     CSRF token goes as both the _token body field and the X-CSRF-Token header.
     ----------------------------------------------------------------------- */
  const csrfToken = () => {
    const meta = doc.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
  };

  const lockForm = (form, submitter) => {
    // Reflect-based `type` catches bare <button> elements too (they default to submit).
    const candidates = findAll('button, input', form);
    if (submitter && candidates.indexOf(submitter) === -1) candidates.push(submitter);

    const locked = [];
    candidates.forEach((btn) => {
      if (!btn || btn.type !== 'submit' || btn.disabled) return;
      btn.disabled = true;
      btn.setAttribute('data-was-enabled', '1');
      locked.push(btn);
    });
    return locked;
  };

  const unlockForm = (locked) => {
    (locked || []).forEach((btn) => {
      if (btn.getAttribute('data-was-enabled') !== '1') return;
      btn.removeAttribute('data-was-enabled');
      btn.disabled = false;
    });
  };

  const applyAjaxPayload = (payload) => {
    if (typeof payload.count !== 'undefined' && payload.count !== null) {
      findAll('[data-cart-count]').forEach((el) => {
        el.textContent = String(payload.count);
      });
    }
    if (typeof payload.lines_html === 'string') {
      findAll('[data-cart-lines]').forEach((el) => {
        el.innerHTML = payload.lines_html;
      });
    }
    if (typeof payload.total_text === 'string') {
      findAll('[data-cart-total]').forEach((el) => {
        el.innerHTML = payload.total_text;
      });
    }
    if (typeof payload.coupon_html === 'string') {
      findAll('[data-coupon-slot]').forEach((el) => {
        el.innerHTML = payload.coupon_html;
      });
    }
    if (typeof payload.status_html === 'string') {
      findAll('[data-order-status]').forEach((el) => {
        el.innerHTML = payload.status_html;
      });
    }
  };

  const goElsewhere = (url) => {
    if (!url || typeof url !== 'string') return;
    if (url.charAt(0) === '#') {
      win.location.hash = url;
      return;
    }
    win.location.assign(url);
  };

  const handleJson = (payload, form) => {
    const ok = payload.ok === true;
    const message =
      typeof payload.message === 'string' ? payload.message : ok ? 'Done.' : 'Something went wrong.';

    if (!ok && payload.code === 'csrf_mismatch') {
      // Stale session/token: a plain reload re-issues the nonce + token.
      win.location.reload();
      return;
    }

    toast(message, ok ? 'ok' : 'fail');

    if (ok) {
      applyAjaxPayload(payload);
      if (typeof payload.redirect === 'string' && payload.redirect) {
        win.setTimeout(() => goElsewhere(payload.redirect), 350);
      }
    }
    if (form) form.dispatchEvent(new win.Event('zapsub:ajax-done', { bubbles: true }));
  };

  const initAjaxForms = () => {
    if (typeof win.fetch !== 'function') return; // native submit path, untouched

    findAll('form[data-ajax]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        if (form.getAttribute('data-ajax-busy') === '1') {
          // A fetch is already in flight: swallow the duplicate, buttons are locked anyway.
          event.preventDefault();
          return;
        }

        // We are about to handle it ourselves, so only now suppress native submit.
        event.preventDefault();

        const submitter = event.submitter;
        const body = new FormData(form, submitter || undefined);
        const token = csrfToken();
        if (token) body.set('_token', token);

        const method = (form.getAttribute('method') || 'post').toUpperCase();
        const url = form.getAttribute('action') || win.location.href;
        const locked = lockForm(form, submitter);
        form.setAttribute('data-ajax-busy', '1');

        const failReload = () => win.location.reload();

        win
          .fetch(url, {
            method: method === 'GET' ? 'GET' : method,
            body: method === 'GET' ? null : body,
            headers: {
              Accept: 'application/json',
              'X-CSRF-Token': token,
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
          })
          .then((response) =>
            response.text().then((text) => {
              let payload;
              try {
                payload = JSON.parse(text);
              } catch (parseError) {
                failReload(); // HTML / login page / error page -> reset page state
                return;
              }
              if (!payload || typeof payload !== 'object') {
                failReload();
                return;
              }
              if (!response.ok && payload.ok === undefined && !payload.message) {
                failReload();
                return;
              }
              handleJson(payload, form);
            })
          )
          .catch(failReload)
          .then(() => {
            form.removeAttribute('data-ajax-busy');
            unlockForm(locked);
          });
      });
    });
  };

  /* --------------------------------------------------------------------------
     WhatsApp chat head — builds wa.me links only, never fires a request.
     ----------------------------------------------------------------------- */
  const WA_SEEN_KEY = 'zapsub_wa_seen';
  const WA_BOUNCE_CLASSES = ['animate-bounce', 'animate-float', 'animate-pulse-ring', 'animate-pop'];
  const WA_DEFAULT_TEXT = 'Hello ZapSub! I have a question about a plan.';

  const storageGet = (key) => {
    try {
      return win.localStorage.getItem(key);
    } catch (storageError) {
      return null; // private mode / blocked storage
    }
  };

  const storageSet = (key, value) => {
    try {
      win.localStorage.setItem(key, value);
    } catch (storageError) {
      /* ignore — the widget still works */
    }
  };

  const digitsOnly = (value) => String(value || '').replace(/\D+/g, '');

  const waLink = (number, message) =>
    'https://wa.me/' + digitsOnly(number) + '?text=' + encodeURIComponent(message || WA_DEFAULT_TEXT);

  const initWhatsApp = () => {
    const widget = doc.querySelector('[data-wa-number]') || doc.querySelector('[data-wa-panel]');
    if (!widget) return;

    const compose = doc.querySelector('[data-wa-compose]');
    const panel = doc.querySelector('[data-wa-panel]');
    const number = widget.getAttribute('data-wa-number') || '';
    const toggles = findAll('[data-wa-toggle]');

    const markWaSeen = () => {
      if (storageGet(WA_SEEN_KEY) !== '1') return;
      widget.classList.add('is-wa-seen');
      toggles.forEach((btn) => {
        btn.classList.add('is-wa-seen');
        btn.classList.remove.apply(btn.classList, WA_BOUNCE_CLASSES);
      });
    };

    const markSeen = () => {
      storageSet(WA_SEEN_KEY, '1');
      markWaSeen();
    };

    // Returning visitors: the bubble stops bouncing.
    markWaSeen();

    // Give a bare/absent compose href something useful (never clobber a real one).
    if (compose && number) {
      const current = compose.getAttribute('href');
      if (!current || current === '#') compose.setAttribute('href', waLink(number, WA_DEFAULT_TEXT));
    }

    // Quick replies only rewrite the composer link — zero network calls here.
    findAll('[data-wa-quick]').forEach((btn) => {
      if (!btn.hasAttribute('type')) btn.setAttribute('type', 'button');
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        if (!compose || !number) return;
        compose.setAttribute(
          'href',
          waLink(number, btn.getAttribute('data-wa-quick') || WA_DEFAULT_TEXT)
        );
      });
    });

    if (!panel) return;

    const setOpen = (open) => {
      if (open) panel.removeAttribute('hidden');
      else panel.setAttribute('hidden', '');
      toggles.forEach((btn) => btn.setAttribute('aria-expanded', open ? 'true' : 'false'));
      if (open) {
        widget.classList.add('is-wa-open');
        markSeen();
      } else {
        widget.classList.remove('is-wa-open');
      }
    };

    toggles.forEach((btn) => {
      if (!btn.hasAttribute('aria-expanded')) btn.setAttribute('aria-expanded', 'false');
      if (!btn.hasAttribute('type')) btn.setAttribute('type', 'button');
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        setOpen(panel.hasAttribute('hidden'));
      });
    });
  };

  /* --------------------------------------------------------------------------
     [data-marquee] — clone the track once so -50% looping is seamless.
     ----------------------------------------------------------------------- */
  const initMarquee = () => {
    findAll('[data-marquee]').forEach((row) => {
      if (row.querySelector('[data-marquee-dupe]')) return; // already doubled
      const track = row.querySelector('[data-marquee-track]') || row.firstElementChild;
      if (!track) return;

      const dupe = track.cloneNode(true);
      dupe.setAttribute('data-marquee-dupe', '');
      dupe.setAttribute('aria-hidden', 'true');
      dupe.removeAttribute('id');
      findAll('[id]', dupe).forEach((node) => node.removeAttribute('id'));
      findAll('input, select, textarea, button', dupe).forEach((node) => {
        node.setAttribute('tabindex', '-1');
        node.setAttribute('disabled', '');
      });
      track.parentNode.insertBefore(dupe, track.nextSibling);
    });
  };

  /* --------------------------------------------------------------------------
     [data-year] — footer copyright.
     ----------------------------------------------------------------------- */
  const initYear = () => {
    const year = String(new Date().getFullYear());
    findAll('[data-year]').forEach((el) => {
      el.textContent = year;
    });
  };

  /* --------------------------------------------------------------------------
     [data-copy] — clipboard with an insecure-context fallback.
     ----------------------------------------------------------------------- */
  const fallbackCopy = (button, source) => {
    const parent = button.parentElement;
    const field =
      (source && source.querySelector('[data-copy-source]')) ||
      (parent && parent.querySelector('[data-copy-source]'));

    if (field && (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA')) {
      field.focus();
      field.select();
      toast('Tap the field and copy', 'ok');
      return;
    }

    const textNode = source || button;
    if (typeof win.getSelection === 'function' && typeof doc.createRange === 'function') {
      const range = doc.createRange();
      range.selectNodeContents(textNode);
      const selection = win.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      toast('Text selected — press Ctrl+C', 'ok');
      return;
    }
    toast('Copy is unavailable here', 'fail');
  };

  const initCopy = () => {
    findAll('[data-copy]').forEach((button) => {
      const source = button.querySelector('[data-copy-value]') || null;
      const value = () => {
        const explicit = button.getAttribute('data-copy-value');
        if (explicit) return explicit;
        if (source) return source.textContent.trim();
        return button.textContent.trim();
      };

      if (button.tagName === 'BUTTON' && !button.hasAttribute('type')) {
        button.setAttribute('type', 'button'); // never accidentally submit a form
      }

      button.addEventListener('click', (event) => {
        const href = button.getAttribute('href');
        if (button.tagName === 'A' && href && href !== '#') return; // real link: let it navigate
        event.preventDefault();
        const text = value();
        if (!text) return;

        const clipboard = typeof navigator !== 'undefined' && navigator.clipboard;
        if (clipboard && typeof clipboard.writeText === 'function' && win.isSecureContext !== false) {
          clipboard
            .writeText(text)
            .then(() => toast('Copied: ' + text, 'ok'))
            .catch(() => fallbackCopy(button, source));
          return;
        }
        fallbackCopy(button, source);
      });
    });
  };

  /* --------------------------------------------------------------------------
     Boot
     ----------------------------------------------------------------------- */
  const modules = [
    initReveal,
    initCounters,
    initTabs,
    initAccordion,
    initQty,
    initHeader,
    initMobileNav,
    initAjaxForms,
    initWhatsApp,
    initMarquee,
    initYear,
    initCopy,
  ];

  const boot = () => {
    modules.forEach((module) => {
      try {
        module();
      } catch (error) {
        // A broken widget must never take the storefront down.
        if (typeof win.console !== 'undefined' && win.console.warn) {
          win.console.warn('ZapSub UI module skipped:', error && error.message);
        }
      }
    });
  };

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
