/**
 * DexxoxxBD storefront behaviour.
 *
 * Loaded as an external file because the CSP only allows inline scripts with a
 * per-request nonce. Every enhancement is progressive: the same actions work as
 * plain form posts and links when JavaScript is unavailable, so nothing here is
 * required to browse, choose a term or add it to the cart.
 *
 * No markup is ever built from a response with innerHTML. Text goes through
 * textContent and attributes through setAttribute, so catalogue content that an
 * admin edited cannot smuggle a tag into the page.
 */

'use strict';

(function () {
    const Dx = {};

    Dx.$ = (selector, scope) => (scope || document).querySelector(selector);
    Dx.$$ = (selector, scope) => Array.from((scope || document).querySelectorAll(selector));

    Dx.money = (value) => new Intl.NumberFormat('en-BD', { maximumFractionDigits: 0 }).format(value);

    Dx.csrf = (form) => {
        const input = form ? form.querySelector('input[name="_token"]') : null;
        return input ? input.value : '';
    };

    Dx.json = (url, options) => fetch(url, options)
        .then((response) => response.json().then((body) => ({ status: response.status, body })))
        .then(({ status, body }) => {
            if (status >= 200 && status < 300 && body && body.ok) {
                return body;
            }
            throw Object.assign(new Error((body && body.message) || 'Request failed'), { status, body });
        })
        .catch((error) => {
            if (error && error.message) {
                throw error;
            }
            throw new Error('Network problem - please try again.');
        });

    Dx.toast = (title, text, icon) => {
        if (!window.Swal) {
            return;
        }
        window.Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon || 'success',
            title: title,
            text: text,
            showConfirmButton: false,
            timer: 3200,
            timerProgressBar: true,
        });
    };

    /** Header type-ahead. */
    Dx.search = function () {
        const box = Dx.$('[data-search]');
        if (!box) {
            return;
        }
        const input = Dx.$('input[name="q"]', box);
        const panel = Dx.$('[data-search-results]', box);
        const endpoint = box.getAttribute('data-endpoint');
        if (!input || !panel || !endpoint) {
            return;
        }

        let timer = null;
        let active = -1;
        let items = [];

        const close = () => {
            panel.hidden = true;
            panel.innerHTML = '';
            items = [];
            active = -1;
            input.removeAttribute('aria-activedescendant');
        };

        const render = (list) => {
            panel.innerHTML = '';
            if (!list.length) {
                const empty = document.createElement('p');
                empty.className = 'dx-suggestions__empty';
                empty.textContent = 'No matches yet - press Enter to search everything.';
                panel.append(empty);
                panel.hidden = false;
                return;
            }

            const group = document.createElement('ul');
            group.className = 'dx-suggestions__list';
            list.forEach((item, index) => {
                const li = document.createElement('li');
                const link = document.createElement('a');
                link.className = 'dx-suggestions__item';
                link.href = item.url;
                link.id = 'dx-suggest-' + index;

                if (item.image) {
                    const thumb = document.createElement('img');
                    thumb.className = 'dx-suggestions__thumb';
                    thumb.alt = '';
                    thumb.loading = 'lazy';
                    thumb.src = item.image;
                    link.append(thumb);
                }

                const label = document.createElement('span');
                label.textContent = item.label;
                const kind = document.createElement('span');
                kind.className = 'dx-suggestions__kind';
                kind.textContent = item.type;
                link.append(label, kind);
                li.append(link);
                group.append(li);
            });
            panel.append(group);
            panel.hidden = false;
            items = Dx.$$('a', group);
        };

        const lookup = (term) => {
            Dx.json(endpoint + '?q=' + encodeURIComponent(term))
                .then((body) => render(body.data || []))
                .catch(close);
        };

        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            const term = input.value.trim();
            if (term.length < 2) {
                close();
                return;
            }
            timer = window.setTimeout(() => lookup(term), 220);
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
                return;
            }
            if (!items.length) {
                return;
            }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                active = (active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items.forEach((link, index) => link.classList.toggle('is-active', index === active));
                items[active].scrollIntoView({ block: 'nearest' });
                input.setAttribute('aria-activedescendant', items[active].id);
            }
            if (event.key === 'Enter' && active > -1) {
                event.preventDefault();
                items[active].click();
            }
        });

        document.addEventListener('click', (event) => {
            if (!box.contains(event.target)) {
                close();
            }
        });

        input.addEventListener('blur', () => {
            window.setTimeout(() => {
                if (!box.contains(document.activeElement)) {
                    close();
                }
            }, 120);
        });
    };

    /** Grid / list switch for the catalog. */
    Dx.viewSwitch = function () {
        const switcher = Dx.$('[data-view-switch]');
        const results = Dx.$('[data-results]');
        if (!switcher || !results) {
            return;
        }

        const stored = window.localStorage.getItem('dx-view');
        if (stored === 'grid' || stored === 'list') {
            results.classList.toggle('dx-grid--list', stored === 'list');
        }

        switcher.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-view]');
            if (!button) {
                return;
            }
            const mode = button.getAttribute('data-view');
            results.classList.toggle('dx-grid--list', mode === 'list');
            Dx.$$('button', switcher).forEach((other) => {
                other.setAttribute('aria-pressed', String(other === button));
            });
            window.localStorage.setItem('dx-view', mode);
        });
    };

    /** Filter panel submits itself; text inputs wait for a pause. */
    Dx.filters = function () {
        const form = Dx.$('[data-filters]');
        if (!form) {
            return;
        }
        let timer = null;

        form.addEventListener('change', (event) => {
            if (event.target.matches('select, input[type="checkbox"], input[type="radio"]')) {
                form.requestSubmit();
            }
        });

        form.addEventListener('input', (event) => {
            if (!event.target.matches('input[type="number"], input[type="search"]')) {
                return;
            }
            window.clearTimeout(timer);
            timer = window.setTimeout(() => form.requestSubmit(), 600);
        });

        Dx.$('[data-filters-clear]', form)?.addEventListener('click', () => {
            Dx.$$('input, select', form).forEach((field) => {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = false;
                } else if (field.type !== 'hidden') {
                    field.value = '';
                }
            });
        });
    };

    /** Product page: pick a term, the buy box follows. */
    Dx.plans = function () {
        const page = Dx.$('[data-plan-selector]');
        if (!page) {
            return;
        }
        const options = Dx.$$('input[name="variation_id"]', page);
        const price = Dx.$('[data-plan-price]', page);
        const compare = Dx.$('[data-plan-compare]', page);
        const saving = Dx.$('[data-plan-saving]', page);
        const duration = Dx.$('[data-plan-duration]', page);
        const sku = Dx.$('[data-plan-sku]', page);
        const stock = Dx.$('[data-plan-stock]', page);
        const submit = Dx.$('button[type="submit"]', page);
        const quantity = Dx.$('input[name="quantity"]', page);

        const apply = (input) => {
            options.forEach((other) => {
                other.closest('.dx-plan-option')?.classList.toggle('is-selected', other === input);
            });
            if (!input) {
                return;
            }

            const data = input.dataset;
            if (price) {
                price.textContent = '\u09f3 ' + Dx.money(data.price);
            }
            if (compare) {
                const list = Number(data.comparePrice || 0);
                compare.textContent = list > Number(data.price) ? '\u09f3 ' + Dx.money(list) : '';
                compare.hidden = !(list > Number(data.price));
            }
            if (saving) {
                const list = Number(data.comparePrice || 0);
                const off = list > Number(data.price) ? Math.round(((list - data.price) / list) * 100) : 0;
                saving.textContent = off > 0 ? 'Save ' + off + '%' : '';
                saving.hidden = off <= 0;
            }
            if (duration) {
                duration.textContent = data.durationLabel || '';
            }
            if (sku) {
                sku.textContent = data.sku || '';
            }
            if (stock) {
                const left = Number(data.stock);
                stock.textContent = left === -1
                    ? 'Restocked automatically'
                    : (left > 0 ? 'Available now - ' + left + ' left' : 'Out of stock');
            }
            if (submit) {
                const left = Number(data.stock);
                submit.disabled = left === 0;
            }
            if (quantity && data.minQuantity) {
                quantity.min = data.minQuantity;
                quantity.max = data.maxQuantity;
                if (Number(quantity.value) < Number(data.minQuantity)) {
                    quantity.value = data.minQuantity;
                }
            }
        };

        options.forEach((input) => input.addEventListener('change', () => apply(input)));
        apply(options.find((input) => input.checked) || null);
    };

    /** Quantity steppers clamp to the term's own min and max. */
    Dx.quantity = function () {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-qty-step]');
            if (!button) {
                return;
            }
            const field = button.parentElement && button.parentElement.querySelector('input[type="number"]');
            if (!field) {
                return;
            }
            const step = Number(button.getAttribute('data-qty-step'));
            const min = Number(field.min || 1);
            const max = Number(field.max || 99);
            const next = Math.min(max, Math.max(min, Number(field.value || min) + step));
            field.value = String(next);
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };

    /** Gallery thumbnails swap the main image. */
    Dx.gallery = function () {
        const gallery = Dx.$('[data-gallery]');
        if (!gallery) {
            return;
        }
        const main = Dx.$('[data-gallery-main]', gallery);
        gallery.addEventListener('click', (event) => {
            const thumb = event.target.closest('button[data-full]');
            if (!thumb || !main) {
                return;
            }
            main.src = thumb.getAttribute('data-full');
            main.alt = thumb.getAttribute('data-alt') || main.alt;
            Dx.$$('button', gallery).forEach((other) => {
                other.classList.toggle('is-active', other === thumb);
                other.setAttribute('aria-current', String(other === thumb));
            });
        });
    };

    /** Quick view dialog, filled from the JSON endpoint. */
    Dx.quickView = function () {
        let dialog = Dx.$('#dx-quick-view');
        if (!dialog) {
            return;
        }
        const title = Dx.$('[data-qv-title]', dialog);
        const text = Dx.$('[data-qv-text]', dialog);
        const image = Dx.$('[data-qv-image]', dialog);
        const price = Dx.$('[data-qv-price]', dialog);
        const rating = Dx.$('[data-qv-rating]', dialog);
        const link = Dx.$('[data-qv-link]', dialog);
        const plans = Dx.$('[data-qv-plans]', dialog);
        const form = Dx.$('form[data-cart-add]', dialog);

        const fill = (data) => {
            title.textContent = data.name;
            text.textContent = data.short_description;
            image.src = data.image;
            image.alt = data.name;
            price.textContent = '\u09f3 ' + Dx.money(data.from_price) + ' onward';
            rating.textContent = Number(data.rating_avg) > 0
                ? data.rating_avg.toFixed(1) + ' from ' + data.rating_count + ' reviews'
                : 'No reviews yet';
            link.href = data.url;
            link.textContent = 'Full details for ' + data.name;
            if (form) {
                form.querySelector('input[name="product_id"]').value = data.id;
            }

            plans.innerHTML = '';
            (data.plans || []).forEach((plan) => {
                const li = document.createElement('li');
                li.className = 'dx-quickview__plan';

                const label = document.createElement('span');
                label.textContent = plan.label;
                const value = document.createElement('strong');
                value.textContent = '\u09f3 ' + Dx.money(plan.price);

                li.append(label, value);

                if (Number(plan.stock) !== 0 && form) {
                    const add = document.createElement('button');
                    add.type = 'submit';
                    add.className = 'btn btn-pill btn-dx-soft';
                    add.textContent = 'Add';
                    // The variation travels through the hidden field so a single
                    // form (and one CSRF token) covers every term.
                    add.addEventListener('click', () => {
                        form.querySelector('input[name="variation_id"]').value = plan.id;
                    });
                    li.append(add);
                } else if (Number(plan.stock) === 0) {
                    const sold = document.createElement('em');
                    sold.textContent = 'Out of stock';
                    li.append(sold);
                }

                plans.append(li);
            });
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-quick-view]');
            if (!trigger) {
                return;
            }
            event.preventDefault();
            const url = trigger.getAttribute('data-url');
            if (!url) {
                return;
            }
            Dx.$('[data-qv-loading]', dialog).hidden = false;
            dialog.classList.add('is-open');
            dialog.removeAttribute('hidden');
            Dx.json(url)
                .then((body) => {
                    Dx.$('[data-qv-loading]', dialog).hidden = true;
                    fill(body.data);
                })
                .catch((error) => {
                    dialog.classList.remove('is-open');
                    Dx.toast('Quick view', error.message, 'error');
                });
        });

        dialog.addEventListener('click', (event) => {
            if (event.target.closest('[data-qv-close]')) {
                dialog.classList.remove('is-open');
                dialog.setAttribute('hidden', 'hidden');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && dialog.classList.contains('is-open')) {
                dialog.classList.remove('is-open');
                dialog.setAttribute('hidden', 'hidden');
            }
        });
    };

    /** Cart forms post through fetch and update the header count. */
    Dx.cartForms = function () {
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-cart-add]');
            if (!form) {
                return;
            }
            if (!window.fetch || typeof form.requestSubmit !== 'function') {
                return;
            }
            event.preventDefault();

            const button = Dx.$('button[type="submit"]', form);
            const label = button ? button.textContent : '';
            if (button) {
                button.disabled = true;
                button.textContent = 'Adding';
            }

            Dx.json(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': Dx.csrf(form),
                },
                body: JSON.stringify(Object.fromEntries(new FormData(form).entries())),
            })
                .then((body) => {
                    Dx.toast('Added to cart', body.message || 'Your cart is one click away.');
                    const badge = Dx.$('[data-cart-count]');
                    if (badge && body.data && typeof body.data.count !== 'undefined') {
                        badge.textContent = body.data.count;
                        badge.hidden = Number(body.data.count) === 0;
                    }
                })
                .catch((error) => {
                    Dx.toast('Could not add that', error.message, 'error');
                })
                .finally(() => {
                    if (button) {
                        button.disabled = false;
                        button.textContent = label;
                    }
                });
        });
    };

    /** Copy buttons for links and, later, delivered keys. */
    Dx.copy = function () {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-copy]');
            if (!button) {
                return;
            }
            const value = button.getAttribute('data-copy');
            const done = () => Dx.toast('Copied', 'The value is on your clipboard.');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done).catch(() => Dx.toast('Copy failed', value, 'error'));
            } else {
                const helper = document.createElement('textarea');
                helper.value = value;
                helper.setAttribute('aria-hidden', 'true');
                document.body.append(helper);
                helper.select();
                document.execCommand('copy');
                helper.remove();
                done();
            }
        });
    };

    /** Sticky header shadow. */
    Dx.header = function () {
        const header = Dx.$('[data-sticky-header]');
        if (!header) {
            return;
        }
        const mark = () => header.classList.toggle('is-stuck', window.scrollY > 8);
        window.addEventListener('scroll', mark, { passive: true });
        mark();
    };

    /** Tabs on the product page: panels are already in the document. */
    Dx.tabs = function () {
        const tabs = Dx.$('[data-tabs]');
        if (!tabs) {
            return;
        }
        const buttons = Dx.$$('[data-tab]', tabs);
        const show = (name) => {
            buttons.forEach((button) => {
                const on = button.getAttribute('data-tab') === name;
                button.classList.toggle('is-active', on);
                button.setAttribute('aria-selected', String(on));
                const panel = Dx.$('#tab-' + button.getAttribute('data-tab'));
                if (panel) {
                    panel.hidden = !on;
                }
            });
        };
        tabs.addEventListener('click', (event) => {
            const button = event.target.closest('[data-tab]');
            if (button) {
                show(button.getAttribute('data-tab'));
            }
        });
        show((Dx.$('[data-tab][aria-selected="true"]', tabs) || buttons[0] || {}).getAttribute?.('data-tab'));
    };

    Dx.start = function () {
        ['search', 'viewSwitch', 'filters', 'plans', 'quantity', 'gallery', 'quickView', 'cartForms', 'copy',
            'header', 'tabs'].forEach((name) => {
            try {
                Dx[name]();
            } catch (error) {
                window.console.warn('Dexxoxx enhancement "' + name + '" skipped:', error);
            }
        });
    };

    window.Dexxoxx = Dx;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', Dx.start);
    } else {
        Dx.start();
    }
})();
