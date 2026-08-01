# SubPass — Digital Subscriptions Marketplace

SubPass is a full-stack e-commerce platform for selling digital subscriptions such as **Netflix, Spotify, ChatGPT Plus, YouTube Premium**, as well as **site-wide membership plans** (Starter, Plus, Pro).

The application is built using **PHP (PDO)**, **MySQL**, **Vanilla JavaScript**, and **Tailwind CSS (CDN)** with **SSLCommerz** integration for secure online payments.

---

# Features

## Customer

- User Registration & Login
- Session-based Authentication
- Browse Subscription Products
- Product Details
- Membership Plans
- Shopping Cart
- Secure Checkout
- SSLCommerz Payment Integration
- Order History
- Purchased Subscription Passes
- Customer Dashboard

---

## Admin

- Admin Dashboard
- Product Management (CRUD)
- Membership Plan Management
- User Management
- Order Management
- Dashboard Statistics
- Protected Admin Authentication

---

# Tech Stack

### Frontend

- HTML5
- Tailwind CSS (CDN)
- Vanilla JavaScript

### Backend

- PHP 8+
- PDO
- Session Authentication
- REST-style JSON APIs

### Database

- MySQL 8

### Payment Gateway

- SSLCommerz

---

# Requirements

- PHP 8.1+
- MySQL 8+
- Apache Web Server
- SSL Certificate (Production)
- SSLCommerz Merchant Account

---

# Project Structure

```
subpass/
│
├── public/                     # Document Root
│   ├── index.html
│   ├── products.html
│   ├── product-detail.html
│   ├── plans.html
│   ├── cart.html
│   ├── checkout.html
│   ├── login.html
│   ├── register.html
│   ├── dashboard.html
│   │
│   ├── admin/
│   │
│   ├── assets/
│   │   └── js/
│   │
│   └── api/
│       ├── auth.php
│       ├── products.php
│       ├── plans.php
│       ├── cart.php
│       ├── orders.php
│       ├── passes.php
│       ├── users.php
│       ├── admin_orders.php
│       ├── admin_stats.php
│       │
│       └── payment/
│           ├── init.php
│           ├── success.php
│           ├── fail.php
│           ├── cancel.php
│           └── ipn.php
│
├── config/
├── includes/
├── database/
│   └── schema.sql
│
├── .env.example
├── Dockerfile
├── docker-compose.yml
├── docker-entrypoint.sh
└── README.md
```

---

# Deployment Compatibility

| Platform | Supported |
|----------|-----------|
| Webuzo | ✅ |
| cPanel | ✅ |
| Apache | ✅ |
| Docker | ✅ |
| Render | ✅ |
| Railway | ✅ |
| Fly.io | ✅ |
| VPS | ✅ |

---

# Option A — Deploy on Webuzo / cPanel (Recommended)

SubPass is designed using a secure directory layout.

Only the **public** directory should be accessible from the web.

```
subpass/
├── config/
├── database/
├── includes/
└── public/
```

This keeps database credentials and application files outside the web root.

## Step 1 — Upload Project

Upload the **subpass** folder to your hosting account.

You do **not** need to upload Docker-related files unless you want to keep them for future deployments.

---

## Step 2 — Configure Document Root

Point your domain or subdomain's Document Root to:

```
subpass/public
```

This is the recommended deployment method.

If your hosting provider doesn't allow changing the Document Root, move the contents of `public/` into `public_html` while keeping the provided `.htaccess` protection for sensitive directories.

---

## Step 3 — Create Database

Create a MySQL database from Webuzo/cPanel.

Import:

```
database/schema.sql
```

This creates:

- Products
- Membership Plans
- Admin Account
- Sample Data

Default Admin:

Email:

```
admin@subpass.test
```

Password

```
Admin@123
```

**Change this password immediately after deployment.**

---

## Step 4 — Configure Environment Variables

Copy values from

```
.env.example
```

Configure them using your hosting panel.

If your hosting does not support environment variables, temporarily configure them inside:

```
config/config.php
```

Never commit production credentials.

---

## Step 5 — Configure SSLCommerz

Add:

- Store ID
- Store Password

Use Sandbox credentials during development.

Switch to Production credentials before launch.

---

## Step 6 — Test Application

Verify:

- Registration
- Login
- Product Listing
- Membership Plans
- Cart
- Checkout
- SSLCommerz Payment
- Customer Dashboard
- Admin Panel

---

# Option B — Deploy with Docker

Docker deployment is intended for:

- Render
- Railway
- Fly.io
- VPS
- Local Development

It is **optional** and **not required** for Webuzo or traditional shared hosting.

## Build

```
docker build -t subpass .
```

Run

```
docker run -p 8080:80 --env-file .env subpass
```

---

## Local Development

Copy

```
cp .env.example .env
```

Start

```
docker compose up --build
```

Visit

```
http://localhost:8080
```

---

# Database Setup

Create database

```sql
CREATE DATABASE subpass;
```

Import

```
database/schema.sql
```

---

# Environment Variables

The most important variables are:

| Variable | Description |
|-----------|-------------|
| DB_HOST | MySQL Host |
| DB_NAME | Database Name |
| DB_USER | Database User |
| DB_PASS | Database Password |
| SITE_URL | Website URL |
| FRONTEND_URL | Frontend URL |
| API_BASE_URL | Backend API URL |
| ALLOWED_ORIGINS | Allowed CORS Origins |
| SSLCZ_STORE_ID | SSLCommerz Store ID |
| SSLCZ_STORE_PASSWORD | SSLCommerz Store Password |
| SSLCZ_IS_SANDBOX | Sandbox Mode |

---

# SSLCommerz Configuration

1. Register for a Sandbox account.

2. Copy your

- Store ID

- Store Password

3. Configure:

```
SSLCZ_STORE_ID
SSLCZ_STORE_PASSWORD
```

4. For Production

```
SSLCZ_IS_SANDBOX=false
```

Use your Live Merchant credentials.

---

# Checkout Flow

```
Cart
    ↓

Create Pending Order

    ↓

Initialize SSLCommerz Payment

    ↓

Hosted Payment Page

    ↓

Payment Validation

    ↓

Mark Order Paid

    ↓

Generate Subscription Pass

    ↓

Customer Dashboard
```

---

# Security

SubPass includes several production-oriented security measures.

- Sensitive directories remain outside the web root.
- PDO Prepared Statements.
- Session-based Authentication.
- HttpOnly Session Cookies.
- Secure Cookies over HTTPS.
- SameSite Cookie Support.
- Restricted CORS Origins.
- Server-side Payment Validation.
- Errors Logged Instead of Displayed.
- No Raw SQL Queries.

---

# Recommended Production Checklist

Before going live:

- Change default admin password
- Enable HTTPS
- Switch SSLCommerz to Live Mode
- Configure Environment Variables
- Disable PHP Error Display
- Enable Automatic Database Backups
- Configure Cron Jobs (if required)
- Test Payment Flow
- Test Admin Permissions
- Verify Session Security

---

# Future Improvements

- Forgot Password
- Email Verification
- Product Image Uploads
- Discount Coupons
- Order Invoice PDF
- Activity Logs
- Login Rate Limiting
- Two-Factor Authentication
- Automated Database Backups

---

# License

This project is intended for educational and commercial use.

Modify and distribute according to your project requirements.
