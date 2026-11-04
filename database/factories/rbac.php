<?php

declare(strict_types=1);

/**
 * The RBAC definition used by both the seeders and the admin screens: the
 * permission catalogue and the role -> permission map. Slugs here are the exact
 * strings used by the `permission:` route middleware, so a typo fails at the
 * door instead of silently granting nothing.
 *
 * @return array{permissions: list<array{slug: string, name: string, description: string, module: string}>, roles: list<array<string, mixed>>}
 */

$catalogue = [
    'dashboard' => [
        ['slug' => 'dashboard.access', 'name' => 'Open the admin panel', 'description' => 'Baseline gate for every administrative screen.'],
        ['slug' => 'reports.view', 'name' => 'View dashboards and reports', 'description' => 'Revenue, sales and inventory analytics.'],
    ],
    'products' => [
        ['slug' => 'products.view', 'name' => 'View products', 'description' => 'Read the catalog including drafts.'],
        ['slug' => 'products.create', 'name' => 'Create products', 'description' => 'Add new subscriptions and their plans.'],
        ['slug' => 'products.update', 'name' => 'Edit products', 'description' => 'Change names, prices, plans and SEO data.'],
        ['slug' => 'products.archive', 'name' => 'Publish or archive products', 'description' => 'Control storefront visibility.'],
        ['slug' => 'products.delete', 'name' => 'Soft-delete products', 'description' => 'Hide a product permanently from the catalog.'],
        ['slug' => 'categories.manage', 'name' => 'Manage categories', 'description' => 'Create and reorder the category tree.'],
        ['slug' => 'brands.manage', 'name' => 'Manage brands', 'description' => 'Maintain service brands.'],
    ],
    'orders' => [
        ['slug' => 'orders.view', 'name' => 'View orders', 'description' => 'Read order and payment details.'],
        ['slug' => 'orders.update_status', 'name' => 'Change order status', 'description' => 'Move an order along the fulfilment path.'],
        ['slug' => 'orders.deliver', 'name' => 'Deliver an order', 'description' => 'Assign inventory and mark items delivered.'],
        ['slug' => 'orders.note', 'name' => 'Add internal notes', 'description' => 'Notes visible to staff only.'],
        ['slug' => 'orders.refund', 'name' => 'Refund an order', 'description' => 'Issue full or partial refunds.'],
        ['slug' => 'payments.verify', 'name' => 'Verify payments', 'description' => 'Confirm a manual or pending payment.'],
        ['slug' => 'payments.view', 'name' => 'View payment log', 'description' => 'Read gateway events and transactions.'],
    ],
    'inventory' => [
        ['slug' => 'inventory.view', 'name' => 'View digital inventory', 'description' => 'See stock levels without revealing secrets.'],
        ['slug' => 'inventory.import', 'name' => 'Import credentials', 'description' => 'Upload licence keys or codes in bulk.'],
        ['slug' => 'inventory.manage', 'name' => 'Manage inventory records', 'description' => 'Expire, revoke or correct inventory rows.'],
        ['slug' => 'inventory.assign', 'name' => 'Assign credentials to orders', 'description' => 'Attach a key to a paid order line.'],
        ['slug' => 'inventory.reveal', 'name' => 'Reveal a credential', 'description' => 'Decrypt a stored key for a support case. Audited.'],
    ],
    'customers' => [
        ['slug' => 'customers.view', 'name' => 'View customers', 'description' => 'Read customer profiles and history.'],
        ['slug' => 'customers.update_status', 'name' => 'Suspend or reactivate accounts', 'description' => 'Control account access.'],
        ['slug' => 'customers.note', 'name' => 'Add customer notes', 'description' => 'Internal remarks on an account.'],
    ],
    'promotions' => [
        ['slug' => 'coupons.view', 'name' => 'View coupons', 'description' => 'Read coupon definitions and usage.'],
        ['slug' => 'coupons.manage', 'name' => 'Manage coupons', 'description' => 'Create, restrict and retire discount codes.'],
    ],
    'engagement' => [
        ['slug' => 'reviews.view', 'name' => 'View reviews', 'description' => 'Read all submitted reviews.'],
        ['slug' => 'reviews.moderate', 'name' => 'Moderate reviews', 'description' => 'Approve, reject and answer reviews.'],
        ['slug' => 'tickets.view', 'name' => 'View support tickets', 'description' => 'Read tickets across the team queue.'],
        ['slug' => 'tickets.reply', 'name' => 'Reply to tickets', 'description' => 'Post staff replies.'],
        ['slug' => 'tickets.assign', 'name' => 'Assign tickets', 'description' => 'Route tickets to another agent.'],
        ['slug' => 'tickets.close', 'name' => 'Close tickets', 'description' => 'Resolve or close a conversation.'],
        ['slug' => 'newsletter.manage', 'name' => 'Manage newsletter list', 'description' => 'Export and clean the subscriber list.'],
    ],
    'cms' => [
        ['slug' => 'pages.manage', 'name' => 'Manage pages', 'description' => 'Edit static content pages.'],
        ['slug' => 'faqs.manage', 'name' => 'Manage FAQs', 'description' => 'Edit site FAQs.'],
        ['slug' => 'banners.manage', 'name' => 'Manage banners', 'description' => 'Control homepage and category banners.'],
        ['slug' => 'testimonials.manage', 'name' => 'Manage testimonials', 'description' => 'Curate homepage testimonials.'],
        ['slug' => 'menus.manage', 'name' => 'Manage menus', 'description' => 'Edit header and footer navigation.'],
        ['slug' => 'settings.manage', 'name' => 'Manage site settings', 'description' => 'Change brand, contact and checkout settings.'],
    ],
    'administration' => [
        ['slug' => 'roles.view', 'name' => 'View roles and permissions', 'description' => 'Read the RBAC matrix.'],
        ['slug' => 'roles.manage', 'name' => 'Manage roles and permissions', 'description' => 'Grant or revoke abilities and assign roles.'],
        ['slug' => 'audit.view', 'name' => 'View audit trail', 'description' => 'Read the privileged-action log.'],
        ['slug' => 'logs.view', 'name' => 'View system logs', 'description' => 'Read the application log stream.'],
        ['slug' => 'jobs.manage', 'name' => 'Manage background jobs', 'description' => 'Retry, cancel and inspect queued work.'],
        ['slug' => 'backups.run', 'name' => 'Run backups', 'description' => 'Trigger database and upload backups.'],
    ],
];

$permissions = [];
$slugs = [];
foreach ($catalogue as $module => $entries) {
    foreach ($entries as $entry) {
        $slugs[] = $entry['slug'];
        $permissions[] = $entry + ['module' => $module];
    }
}

$without = static function (array $except) use ($slugs): array {
    return array_values(array_diff($slugs, $except));
};

return [
    'permissions' => $permissions,

    'roles' => [
        [
            'slug' => 'super-admin',
            'name' => 'Super Admin',
            'description' => 'Full control, including roles, infrastructure and the audit trail.',
            'level' => 1,
            'staff' => true,
            'is_system' => true,
            'permissions' => '*',
        ],
        [
            'slug' => 'admin',
            'name' => 'Admin',
            'description' => 'Runs the shop: catalog, orders, customers, content and reports.',
            'level' => 2,
            'staff' => true,
            'is_system' => true,
            'permissions' => $without(['roles.manage', 'backups.run', 'jobs.manage', 'inventory.reveal']),
        ],
        [
            'slug' => 'product-manager',
            'name' => 'Product Manager',
            'description' => 'Owns the catalog, plans, categories, brands and inventory imports.',
            'level' => 3,
            'staff' => true,
            'is_system' => false,
            'permissions' => [
                'dashboard.access',
                'reports.view',
                'products.view',
                'products.create',
                'products.update',
                'products.archive',
                'categories.manage',
                'brands.manage',
                'inventory.view',
                'inventory.import',
                'inventory.manage',
                'reviews.view',
            ],
        ],
        [
            'slug' => 'order-manager',
            'name' => 'Order Manager',
            'description' => 'Handles payment verification, fulfilment, refunds and delivery.',
            'level' => 4,
            'staff' => true,
            'is_system' => false,
            'permissions' => [
                'dashboard.access',
                'orders.view',
                'orders.update_status',
                'orders.deliver',
                'orders.note',
                'orders.refund',
                'payments.view',
                'payments.verify',
                'inventory.view',
                'inventory.assign',
                'customers.view',
                'coupons.view',
            ],
        ],
        [
            'slug' => 'support-agent',
            'name' => 'Support Agent',
            'description' => 'Answers tickets and reads order context; cannot move money.',
            'level' => 5,
            'staff' => true,
            'is_system' => false,
            'permissions' => [
                'dashboard.access',
                'orders.view',
                'payments.view',
                'customers.view',
                'tickets.view',
                'tickets.reply',
                'tickets.assign',
                'tickets.close',
                'reviews.view',
                'inventory.view',
            ],
        ],
        [
            'slug' => 'customer',
            'name' => 'Customer',
            'description' => 'Storefront account. No administrative abilities.',
            'level' => 100,
            'staff' => false,
            'is_system' => true,
            'permissions' => [],
        ],
    ],
];
