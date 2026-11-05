# ZapSub

A subscription-reseller storefront: colourful catalogue, smooth cart and checkout,
manual mobile-payment verification, a floating WhatsApp chat head, and a full
admin panel. Native PHP 8.5 + Tailwind CSS + MySQL. No framework, no Node needed
on the server.

Reference site for product shape only; the brand, design and code are original.

---

## Requirements

| Component | Needed | Verified with |
| --- | --- | --- |
| PHP | 8.3+ (built on 8.5.12) | `/f/Xamp/php/php.exe -v` |
| MySQL/MariaDB | running, reachable | `127.0.0.1:3307` in this setup |
| Apache | `mod_rewrite`, docroot at `public/` | 2.4.65 |
| Composer | for dependencies | vendor/ is committed here |
| Node/npm | only to rebuild the CSS | optional; `public/assets/css/app.css` is committed |

## Install

```bash
composer install
cp .env.example .env      # then fill in DB_* and the SEED_* passwords
composer key              # writes a fresh APP_KEY into .env
composer migrate          # Phinx creates every table
composer seed             # catalogue, plans, coupons, users, demo orders, inbox
npm install && npm run build:css   # only if you change resources/
```

Point the vhost/docroot at `public/` only. The parent `.htaccess` denies the rest
of the project, so `.env`, `app/`, `storage/`, `database/` and `vendor/` are not
reachable over HTTP (verified: each returns 403).

### Environment

`.env` holds the database credentials, `APP_KEY`, the encryption marker and the
rate-limit budgets (`RATE_LOGIN=5,900` and friends). `SEED_ADMIN_PASSWORD`,
`SEED_STAFF_PASSWORD` and `SEED_CUSTOMER_PASSWORD` feed the user seeder; the
seeder refuses to run with a blank or short value, and nothing like a password is
ever stored in this file's comments or in git.

Read your own values with:

```bash
grep '^SEED_ADMIN_PASSWORD=' .env
```

### Seeding safety

Seeders are idempotent and refuse to touch a shop that has real trade:
`CatalogSeeder`, `UserSeeder` and `DemoOrderSeeder` abort if any order exists whose
number does not start with `ZS-DEMO`. Delete the demonstration data with:

```sql
delete from orders where order_no like 'ZS-DEMO%';
delete from users where email in ('admin@zapsub.test','rafi@zapsub.test','tanvir@example.com','nusrat@example.com');
delete from coupons where code in ('ZAP10','STUDENT15','ANNUAL100','FIRST50','WEEKEND','EID2024','PARTNER30');
```

---

## Layout

```
app/            Core (router, request, session, crypto, limiter), controllers,
                models, services, middleware, exceptions, helpers
config/         php arrays read through Config::get('app.debug') style keys
database/       migrations/ (Phinx) and seeders/
resources/      views/ (layouts, partials, components, pages, admin) and css/input.css
public/         the only web-exposed directory: index.php, assets/, .htaccess
routes/web.php  every route, grouped with middleware
storage/        logs/, rate/ (limiter buckets), cache/ - outside the web root
tests/          bootstrap.php, TestCase.php, Unit/, Mysql/
```

URL: `http://localhost/DexxoxxBD/public/`. The folder is still named `DexxoxxBD`
while the brand is ZapSub; nothing depends on the directory name (`APP_BASE_PATH`
in `.env` handles the sub-folder mount), so rename it if you like.

---

## Money path

1. Cart lives in the session and is re-priced from MySQL on every request, so a
   tampered price never survives a page load.
2. `POST /checkout` writes an order with `payment_status = unpaid`.
3. The customer declares a payment (`bKash`/`Nagad`/`Rocket`/card) with a TrxID.
   That only produces `pending_verification` and a `pending` payment row.
4. An administrator or staff member verifies it in `/admin/orders/{id}`.
   `OrderService::verifyPayment()` is the only code that writes `paid`, and it
   requires a real staff account (`verified_by`, `paid_at`, an audit row).
5. Delivery notes (the credentials handed to the customer) are written through
   `Crypto::encrypt()` - AES-256-GCM, key from `APP_KEY` in `.env`, never in MySQL.

Re-declaring a payment on the same rail rewrites the existing row (the schema keeps
one payment per order+rail) rather than failing. A TrxID already used on another
order is refused; the same string on a different rail is allowed.

## Security

- **Server-side authorization.** `auth` / `staff` / `owner` middleware on the route
  group, checked before the controller runs. Templates hide buttons; the gate is
  what stops the request.
- **CSRF** double-submit token (`_token` field or `X-CSRF-Token` header), compared
  with `hash_equals()`, rotated on failure. 403, not 419 (Apache turns unregistered
  statuses into 500s).
- **Rate limiting** in file buckets under `storage/rate`, sliding window, per
  route type and identity; keeps working when MySQL is down. A blocked attempt is
  still recorded, so hammering cannot extend the lockout.
- **CSP + headers** on every response: `default-src 'self'`, nonce-only inline
  scripts, `frame-ancestors 'self'`, `nosniff`, `Referrer-Policy`, HSTS when TLS is
  detected. No inline event handlers anywhere.
- **Sessions**: `HttpOnly`, `SameSite=Lax`, path-scoped to the app, 48-char id,
  regenerated on login, idle expiry by the configured lifetime.
- **SQL**: prepared statements with bound values only, `EMULATE_PREPARES` off.
- **Passwords**: Argon2id (bcrypt fallback), rehashed transparently on login.
- **Secrets at rest**: `enc:v1$` AES-256-GCM; a tampered ciphertext returns
  `(tampered)` instead of anything readable.
- **Logging** goes to `storage/logs` (day-rotated, `security.log` for gate
  failures). Genuine faults are `ERROR`; expected user-facing failures such as
  validation are `INFO` and never include the submitted values.

## Tests

```bash
composer test            # unit + MySQL suites
composer test:unit       # limiter, crypto, validator, CSRF token, password hash
composer test:mysql      # payment status, coupons, role gates, request gates, SQL safety
```

`tests/bootstrap.php` pins `APP_ENV=testing`, which `config/database.php` resolves
to the separate `<DB_NAME>_test` schema, creates that schema if it is missing and
runs the same Phinx migrations against it. Every test asserts the suffix before it
touches the database, so a run cannot write into the shop data. Fixtures are
prefixed `TEST-` and cleaned up in `tearDown()`.

Current state: 99 tests, 278 assertions, all passing.

`/health` answers booleans only (database, APP_KEY, catalogue seeded) - no row
counts and no fix-it hints unless `APP_DEBUG` is on.

## Customising

- Catalogue: `/admin/services` and `/admin/plans` (1/3/6/12-month tiers, stock,
  badges). `stock = -1` is unlimited, `max_uses = 0` and `per_customer_limit = 0`
  mean unlimited.
- WhatsApp head: `whatsapp.enabled`, `whatsapp.number` (with country code, no `+`),
  `whatsapp.greeting`, `whatsapp.hours`, `whatsapp.default_message`,
  `whatsapp.agent_name` / `agent_role` and `whatsapp.quick_replies` in
  `/admin/settings`. The bubble opens
  `https://wa.me/<number>?text=<prefilled>` and never needs an account.
- Rails: `config/payments.php` plus the bKash/Nagad numbers in settings.
- Look: `resources/css/input.css` holds the `@theme` tokens and keyframes; rebuild
  with `npm run build:css` (or `composer css`).
- The three HTML shells (`layouts/app`, `layouts/admin`, `admin/login`) also load
  `partials/tailwind-cdn.php`, which boots the Tailwind Play CDN and mirrors the
  same `@theme` tokens into `tailwind.config`. The compiled `app.css` stays
  linked because the CDN cannot generate the component layer (`.glass`, `.blob`,
  `.reveal`, `.a-table`, `.text-gradient`) or the `@keyframes`. If you add or
  change a token, edit both files, or the two compilers disagree.
  The CDN logs one console warning per page and is meant for development only:
  to drop it on production, delete the `<?= $view->partial(...tailwind_cdn...) ?>`
  line from the three shells and serve the built CSS alone. Its origin is already
  in `script-src`, so the CSP does not need touching.
