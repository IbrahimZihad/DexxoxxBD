# DexxoxxBD — subscription pass storefront

An online shop that sells time-limited access passes (streaming, cloud, gaming and
design subscriptions) for customers in Bangladesh. A buyer adds a bundle or a single
service to the cart, pays by card through SSLCommerz or by bKash / Nagad / Rocket /
bank transfer, and receives a pass code with a validity window. Staff review manual
payments, manage the catalogue, watch revenue and revoke passes from an admin panel.

Plain PHP 8.1+ and MariaDB. No framework, no Composer dependencies, no bundler, no
CDN and no Docker. Every page is static HTML plus one hand-written stylesheet set and
`assets/js/core.js`; all data flows through JSON endpoints under `public/api/`.

---

## 1. Requirements

| Piece | Needed |
| --- | --- |
| PHP | 8.1 or newer, with `pdo_mysql`, `json`, `mbstring`, `openssl` |
| Database | MariaDB 10.4+ or MySQL 8 |
| Web server | Apache with `mod_headers` (`.htaccess` CSP) — or nginx with an equivalent header block |
| CLI | the same PHP binary the server uses, so `.env` and extensions match |

On XAMPP the CLI is `F:\Xamp\php\php.exe` (adjust the drive letter if yours differs);
`php` is usually not on `PATH`, so use the full path in the commands below.

---

## 2. Install

```bash
# 1. Put the project where the web server can reach it, e.g.
#    F:\Xamp\htdocs\DexxoxxBD

# 2. Create the environment file and edit it.
cp .env.example .env

# 3. Build the schema. The database itself is created if it does not exist.
php database/migrate.php up

# 4. Load the demo catalogue, accounts and settings.
php database/seed.php

# 5. Open the storefront.
http://localhost/DexxoxxBD/public/
```

`.env` keys worth knowing:

| Key | Notes |
| --- | --- |
| `APP_ENV` | `local` turns debug output on; anything else is treated as production |
| `SITE_URL` / `API_BASE_URL` / `FRONTEND_URL` | absolute URLs; on XAMPP the pages live under `/DexxoxxBD/public` |
| `DB_*` | credentials for MariaDB. The CLI creates `DB_NAME` when missing |
| `SSLCZ_STORE_ID` / `SSLCZ_STORE_PASSWORD` | leave blank to disable card checkout — manual payments keep working |
| `TRUSTED_PROXIES` | comma-separated proxy IPs. `X-Forwarded-For` is ignored unless the peer is listed here |
| `THROTTLE_*` | rate-limit budgets per window |

Never commit `.env`. Nothing in `.env.example` is a working credential.

### Reset / start over

```bash
php database/migrate.php status          # what has run, what is pending
php database/migrate.php fresh --force   # DROP every table in the database, then re-run all migrations
php database/seed.php
```

`005_upgrade_legacy_schema.sql` exists so an untouched copy of the original site can
be migrated in place; on a fresh database its statements fail only with
"already exists" errors, which the runner reports as skips.

### Seed data

* 5 categories, 12 products, 3 bundles (Netflix/Spotify/Canva-shaped demos)
* 4 accounts — passwords below
* 17 settings rows (payment numbers, support contacts, storefront copy)

The payment numbers in the seeder are placeholders (`01700000000` and friends). They
are what customers are told to send money to, so replace them from
**Admin → Settings** before taking any real order.

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@dexxoxxbd.test` | `Admin@2026` |
| Customer | `customer@dexxoxxbd.test` | `Customer@2026` |
| Customer | `nusrat@dexxoxxbd.test` | `Customer@2026` |
| Customer | `tanvir@dexxoxxbd.test` | `Customer@2026` |

Change all four (or delete the demo accounts) before deploying.

---

## 3. Layout

```
.
├── bin/sweep.php            cron entry: expire unpaid orders, lapse passes
├── config/config.php        pure data; every value comes from .env
├── database/
│   ├── cli-boot.php         shared bootstrap for the CLI tools
│   ├── migrate.php          status | up | fresh --force
│   ├── seed.php             idempotent upserts
│   ├── migrations/          001…005, tracked in schema_migrations
│   └── seeds/               categories, products, plans, users, settings
├── public/                  the only web-accessible directory
│   ├── api/                 thin dispatchers, one per URL
│   ├── api/payment/         init, methods, manual, success, fail, ipn, cancel
│   ├── assets/css/          app.css (design system), admin.css (panel shell)
│   ├── assets/js/           core.js + one script per page
│   ├── admin/               panel pages, sharing admin.js
│   └── *.html               storefront pages
├── routes/api.php           method, auth, CSRF and throttle rules per route
├── src/App/                 layered core, autoloaded from src/
│   ├── Core/                Env, Config, Database, Logger, Kernel
│   ├── Http/                Request, Response, Controller
│   ├── Auth/                Session, Auth
│   ├── Security/            Hash, Sanitizer, Validator, RateLimiter, Csrf, Headers
│   ├── Repositories/        one per table group, all prepared statements
│   ├── Services/            Order, Payment, Pass, ManualPayment, Stats, Maintenance
│   ├── Payment/             SslCommerz gateway client
│   ├── Controllers/         + Controllers/Admin
│   ├── Exceptions/          HttpException and friends
│   └── bootstrap.php        autoloader + error handling
└── storage/logs/            application log (never served)
```

`src/`, `config/`, `routes/`, `database/`, `storage/` and `bin/` each contain a
`.htaccess` that denies HTTP access, and the project root has one that refuses dotfiles
so a document root pointed at the project folder still cannot leak `.env`.

---

## 4. How a request flows

`public/api/products.php` is five lines: it requires `src/bootstrap.php` and hands the
path to the kernel. The kernel then, in this order:

1. loads config, starts the hardened session, applies security headers and CORS;
2. reads the route definition from `routes/api.php`, keyed by the dispatcher filename;
3. enforces the throttle bucket, then the auth requirement, then the CSRF check;
4. calls `Controller::action()` — `?action=token` wins over the HTTP verb;
5. converts thrown `HttpException`s into JSON error envelopes with the right status.

Route metadata looks like this, and it is the single source of truth for access control:

```php
'orders.php' => [
    'GET'  => ['handler' => [OrderController::class, 'index'], 'auth' => 'user', 'throttle' => 'read'],
    'POST' => ['handler' => [OrderController::class, 'store'], 'auth' => 'user', 'csrf' => true, 'throttle' => 'orders.create'],
],
```

The client only ever sends `X-CSRF-Token`, obtained from `GET api/auth.php?action=token`;
no token is ever placed in a cookie readable by scripts or inlined into a page.

---

## 5. Security model

**Authentication.** bcrypt at cost 12 (`BCRYPT_COST`). Signing in rehashes the stored
digest when the configured cost changed. Login compares against a real placeholder
digest when the account does not exist so that a missing user and a wrong password take
the same time. Roles are `customer` and `admin`; every admin handler calls
`Auth::requireAdmin()` again on the server — hiding a link is never the check.

**Sessions.** Cookie is `HttpOnly`, `Secure` over HTTPS, `SameSite=Lax` on plain HTTP and
`None` over TLS (a split-origin deploy needs it), path-scoped, with the name from `.env`;
strict mode and cookie-only session ids are on. The id regenerates on login and on
privilege change; an idle timeout and an absolute lifetime both come from config. Expired
sessions are destroyed server-side.

**CSRF.** Double-submit comparison of the `X-CSRF-Token` header against the session
value for every mutating route, enforced by the router rather than by individual
controllers. A stale token returns `403` with `code: "csrf_stale"` (419 is rewritten to 500 by
Apache), and `core.js` re-mints and retries once.

**Rate limiting.** Fixed-window counters in the `rate_limits` table, keyed by
bucket + client IP: login 8/15 min, register 5/h, order creation 30/h, payment
initiation 20/h, manual payment submission 20/h, reads 240/min. `429` responses carry a
`Retry-After`.

**Input.** `Validator` whitelists fields, casts types and enforces lengths; `Sanitizer`
strips tags and invisible characters and caps lengths. Every query is a prepared
statement with bound parameters — no string interpolation of user data. `LIKE` patterns
escape `%`, `_` and `\`. Sort columns and status filters are matched against explicit
allow-lists, so an unexpected query parameter reads as "no filter" instead of reaching SQL.

**Responses.** Every controller output is built with `Dx.h` + `textContent` on the
client, so stored XSS from a product name or a payment reference cannot execute. The API
sends `Content-Security-Policy: default-src 'none'`, `X-Content-Type-Options: nosniff`,
`X-Frame-Options: DENY`, `Referrer-Policy`, a `Permissions-Policy`, `Cache-Control: no-store`,
and strips `X-Powered-By`. HSTS is only sent on a TLS connection. CORS is an allow-list
from `ALLOWED_ORIGINS` and varies on `Origin`. Request bodies are capped at 128 KB.

**Money and stock.** Prices are re-read from the database inside the order transaction;
whatever the cart or the browser claims is discarded. Stock is reserved with
`SELECT … FOR UPDATE` and a guarded `UPDATE … WHERE stock >= ?`, so two buyers cannot take
the same last unit. The gateway `amount` is verified server-side before a redirect.
Pass issuing is idempotent and keyed on the order, so a replayed callback cannot mint a
second pass. Gateway completion is only trusted after a server-side `val_id` check over
HTTPS against the gateway's own API — a POST to `ipn_url` alone never grants anything.
Manual payments stay `pending` until a human approves them, and the amount is re-checked
against the order at approval time.

**Logs.** `storage/logs/app.log` records auth events, order creation, payment decisions
and throttled requests with the user id, IP and order number. Passwords, tokens and card
data are never logged.

---

## 6. Payments

**Card (SSLCommerz).** `POST api/payment/init.php` creates the session against the
gateway using the order total recalculated server-side, then the browser follows
`GatewayUrl`. The gateway calls back to `api/payment/success.php`, `fail.php`,
`cancel.php` and `ipn.php`. Each of them re-verifies with `val_id`, compares `tran_id`,
`store_id`, `currency` and `amount` with what was recorded, and only then marks the order
paid and issues passes. Anything already paid is acknowledged without re-granting.
Leaving `SSLCZ_STORE_ID` blank disables the card option and the checkout page shows only
manual channels.

**Manual (bKash / Nagad / Rocket / bank).** Numbers, account titles and instructions live
in the `settings` table and are editable from **Admin → Settings**. The buyer sends the
money, files the transaction id and their sender number, and the order stays pending with
stock held. Staff see the queue with a live badge, open one payment, and either approve
(issues passes, marks paid, records the reviewer and a note) or reject (releases stock,
closes the order, shows the note to the buyer).

**Pending orders** hold stock for `PENDING_ORDER_TTL_MINUTES` (6 h by default).
`bin/sweep.php` releases the ones that lapsed and lapses overdue passes; it is idempotent,
so run it often:

```
*/5 * * * *  php /path/to/DexxoxxBD/bin/sweep.php >> /path/to/DexxoxxBD/storage/logs/sweep.log 2>&1
```

Expiry also runs lazily whenever a passes list or the admin sweep loads, so a site
without cron still behaves. Turning off **Admin → Settings → auto expire passes** holds
expiry manually, which is the switch to use for a goodwill extension.

---

## 7. Frontend rules

The design system lives in `public/assets/css/app.css` (tokens, layout primitives,
components, motion) and `public/admin/assets/css/admin.css` (the panel shell). Colours,
spacing, radii, shadows and timings are CSS custom properties on `:root`; components read
the tokens and never hard-code a colour.

Two constraints are load-bearing because of the CSP, and breaking either one breaks the
page in the browser:

1. **No inline `<script>`.** Every page includes `assets/js/core.js` and then its own
   script file. Nothing else runs JavaScript.
2. **No `style=""` attribute.** Dynamic values go through CSS custom properties
   (`el.style.setProperty('--w', …)`, which is permitted) or through class toggles.

Conventions worth keeping when editing:

* DOM is built with `Dx.h(tag, attrs, …children)` and `Dx.mount`/`Dx.clear`; text always
  goes through `textContent`/`append`. Never assign `innerHTML` with data.
* Links from API content pass `Dx.safeRedirect` (relative paths only) and hrefs that
  leave the site are validated before assignment.
* Motion — scroll reveal with `--i` stagger, count-up stats, sheen and ripple buttons,
  marquee announcement bar, parallax hero, pulse stamps, shimmer skeletons, growing
  sparkline bars — is decorative only and is switched off under
  `@media (prefers-reduced-motion: reduce)`. Nothing is hidden behind an animation: if
  JS never runs, the content is still in the DOM.
* The pass stub (perforated ticket edge, barcode strip, copyable code) is the signature
  component; reuse `.pass-card` rather than inventing a new card shape.

Admin pages are HTML shells: an empty `<body class="admin-page" data-api-base="../api">`
plus three scripts. `admin.js` builds the sidebar, verifies the session with
`Dx.requireAdmin`, and exposes `stat`, `sparkline`, `stamp`, `pager`, `confirmAction` and
`outlet`. `data-api-base` is how the panel points at `../api` without an inline script.

---

## 8. Testing a deployment

```bash
# unauthenticated read
curl -s http://localhost/DexxoxxBD/public/api/products.php | head -c 400

# a mutating call without a token must be refused
curl -s -o /dev/null -w '%{http_code}\n' -X POST \
  http://localhost/DexxoxxBD/public/api/cart.php -H 'Content-Type: application/json' -d '{}'

# the CSP header must be present on a page
curl -sI http://localhost/DexxoxxBD/public/ | grep -i content-security-policy
```

Then in a browser: sign in as the demo customer, add a bundle, check out with a manual
channel, sign in as the admin, approve the payment in **Payment reviews**, and confirm the
pass appears in the customer's dashboard with a working copy button and progress bar.
Open the network panel on each page — a `403` with `code: "csrf_stale"` means the token mint is not reachable, and a
CSP violation console error means an inline style or script crept in.

---

## 9. Going live checklist

1. `APP_ENV=production`, `APP_DEBUG=false`, real `SITE_URL`/`API_BASE_URL`/`FRONTEND_URL` over HTTPS.
2. Replace the four seeded accounts, or delete them and create your own.
3. Put your real bKash / Nagad / Rocket / bank numbers in **Admin → Settings**.
4. Set `SSLCZ_STORE_ID`, `SSLCZ_STORE_PASSWORD`, `SSLCZ_IS_SANDBOX=false` and test one card payment end to end.
5. `ALLOWED_ORIGINS` only if the frontend is hosted apart from the API.
6. `TRUSTED_PROXIES` = your load balancer's IPs, or leave empty.
7. Add the `bin/sweep.php` cron entry.
8. Make sure `storage/` is writable by PHP and not reachable over HTTP.
9. Set a database user with rights on `DB_NAME` only — not `CREATE` on everything.
10. Keep `.env` out of version control and out of backups that leave your machine.

---

## 10. Notes

* **Docker is gone on purpose.** `Dockerfile`, `docker-compose.yml` and the entrypoint
  script were removed; the app is served by any PHP + MariaDB host, so the deployment
  instructions are the XAMPP/Apache steps above.
* Re-running `database/seed.php` is safe: catalogue rows match on slug, settings on key,
  users on email.
* The storefront shows prices with the ৳ symbol. The currency handed to SSLCommerz is
  `SSLCZ_CURRENCY`.
* `README`, `.env` and the source tree are not web locations; if your server ignores
  `.htaccess` (nginx, or `AllowOverride None`), copy the deny rules and the CSP header
  block into the server config — the API sends its own CSP from PHP regardless.
* The endpoints keep the URL shapes of the original project, so an existing integration
  that called `api/orders.php` or `api/payment/init.php` continues to work.
