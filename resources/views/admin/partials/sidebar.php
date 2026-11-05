<?php

declare(strict_types=1);

/**
 * Admin sidebar. One array describes the whole navigation, so a screen added to
 * routes/web.php only needs a line here.
 *
 * Every href comes from route(); nothing is hardcoded, so the panel keeps working
 * when the app moves into a sub-directory. Links the route only lets an
 * administrator through (owner middleware) are hidden from staff instead of
 * showing them a 403 - the gate itself stays in middleware.
 *
 * @var string $currentPath
 */
$isAdmin = (string) ($authUser['role'] ?? '') === 'admin';

/**
 * Icon paths are this file's own whitelist, keyed by name - the same trick the
 * empty-state component uses, so no database or request value reaches the markup.
 *
 * @var array<string, string> $icons
 */
$icons = [
    'desk' => '<path d="M4 13h6V4H4v9Zm10 7h6V4h-6v16Z" stroke-linejoin="round"/>',
    'deliver' => '<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z" stroke-linejoin="round"/><circle cx="7" cy="17" r="1.6"/><circle cx="17" cy="17" r="1.6"/>',
    'orders' => '<path d="M6 3h9l3 3v15H6z" stroke-linejoin="round"/><path d="M9 9h7M9 13h7M9 17h4" stroke-linecap="round"/>',
    'service' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M8 12h8" stroke-linecap="round"/>',
    'plan' => '<path d="M4 7h16v12H4z" stroke-linejoin="round"/><path d="M9 7V4h6v3" stroke-linejoin="round"/>',
    'coupon' => '<path d="M4 8h16v3a2 2 0 0 0 0 4v3H4v-3a2 2 0 0 0 0-4z" stroke-linejoin="round"/><path d="M12 8v12" stroke-dasharray="2 3"/>',
    'inbox' => '<path d="M4 13h4l1.5 3h5L16 13h4" stroke-linejoin="round"/><path d="M4 13 6 5h12l2 8v6H4v-6Z" stroke-linejoin="round"/>',
    'reviews' => '<path d="M12 4l2.4 5 5.6.8-4 3.9 1 5.6-5-2.7-5 2.7 1-5.6-4-3.9 5.6-.8z" stroke-linejoin="round"/>',
    'faq' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5A2.5 2.5 0 1 1 12 13v1.5" stroke-linecap="round"/><path d="M12 18h.01" stroke-linecap="round"/>',
    'customers' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0" stroke-linecap="round"/><path d="M16 6.5a3 3 0 0 1 0 5.5M17 20a5.5 5.5 0 0 0-2-4" stroke-linecap="round"/>',
    'team' => '<path d="M12 3 4 6v6c0 4.4 3.2 8.2 8 9 4.8-.8 8-4.6 8-9V6l-8-3Z" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M4 10h3M17 10h3M6 15h12" stroke-linecap="round"/>',
    'audit' => '<path d="M6 3h9l3 3v15H6z" stroke-linejoin="round"/><path d="m9 13 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>',
];

/**
 * @var list<array{label: string, items: list<array{label: string, name: string, path: string, icon: string, exact?: bool, owner_only?: bool}>}> $groups
 */
$groups = [
    [
        'label' => 'The desk',
        'items' => [
            ['label' => 'Dashboard', 'name' => 'admin.dashboard', 'path' => 'admin', 'icon' => 'desk', 'exact' => true],
            ['label' => 'Delivery queue', 'name' => 'admin.delivery', 'path' => 'admin/delivery', 'icon' => 'deliver'],
            ['label' => 'Orders', 'name' => 'admin.orders', 'path' => 'admin/orders', 'icon' => 'orders'],
        ],
    ],
    [
        'label' => 'Catalogue',
        'items' => [
            ['label' => 'Services', 'name' => 'admin.services', 'path' => 'admin/services', 'icon' => 'service'],
            ['label' => 'Plans', 'name' => 'admin.plans', 'path' => 'admin/plans', 'icon' => 'plan'],
            ['label' => 'Coupons', 'name' => 'admin.coupons', 'path' => 'admin/coupons', 'icon' => 'coupon', 'owner_only' => true],
        ],
    ],
    [
        'label' => 'People',
        'items' => [
            ['label' => 'Messages', 'name' => 'admin.messages', 'path' => 'admin/messages', 'icon' => 'inbox'],
            ['label' => 'Reviews', 'name' => 'admin.testimonials', 'path' => 'admin/reviews', 'icon' => 'reviews'],
            ['label' => 'FAQs', 'name' => 'admin.faqs', 'path' => 'admin/faqs', 'icon' => 'faq'],
            ['label' => 'Customers', 'name' => 'admin.customers', 'path' => 'admin/customers', 'icon' => 'customers', 'owner_only' => true],
        ],
    ],
    [
        'label' => 'Shop',
        'items' => [
            ['label' => 'Team', 'name' => 'admin.team', 'path' => 'admin/team', 'icon' => 'team', 'owner_only' => true],
            ['label' => 'Settings', 'name' => 'admin.settings', 'path' => 'admin/settings', 'icon' => 'settings', 'owner_only' => true],
            ['label' => 'Audit trail', 'name' => 'admin.audit', 'path' => 'admin/audit', 'icon' => 'audit', 'owner_only' => true],
        ],
    ],
];
?>
<nav aria-label="Admin sections" class="space-y-5">
    <?php foreach ($groups as $group): ?>
        <?php
        // A group whose every link is admin-only disappears for staff, so the
        // sidebar does not advertise a half-empty section.
        $visibleItems = array_values(array_filter(
            $group['items'],
            static fn (array $item): bool => $isAdmin || ($item['owner_only'] ?? false) === false
        ));
        ?>
        <?php if ($visibleItems === []) {
            continue;
        } ?>

        <div>
            <p class="px-3 pb-1.5 text-[0.68rem] font-black uppercase tracking-[0.18em] text-ink-400">
                <?= e($group['label']) ?>
            </p>

            <ul class="space-y-0.5">
                <?php foreach ($visibleItems as $item): ?>
                    <?php
                    $isActive = ($item['exact'] ?? false)
                        ? trim((string) ($currentPath ?? ''), '/') === trim($item['path'], '/')
                        : is_current_path($item['path']);
                    $classes = $isActive
                        ? 'bg-brand-600 text-white shadow-glow'
                        : 'text-ink-700 hover:bg-brand-50';
                    ?>
                    <li>
                        <a
                            href="<?= e(route($item['name'])) ?>"
                            class="flex items-center gap-2.5 rounded-brand px-3 py-2 text-sm font-bold transition-colors duration-200 <?= e($classes) ?>"
                            <?php if ($isActive): ?> aria-current="page"<?php endif; ?>
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <?php /* Whitelisted path data from the top of this file. */ ?>
                                <?= $icons[$item['icon']] ?? $icons['orders'] ?>
                            </svg>
                            <span class="truncate"><?= e($item['label']) ?></span>
                            <?php if ($item['owner_only'] ?? false): ?>
                                <span class="ms-auto rounded-pill bg-black/10 px-1.5 py-0.5 text-[0.6rem] font-black uppercase tracking-wide">Admin</span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <div class="rounded-brand bg-brand-50 p-3 text-[0.72rem] leading-relaxed text-brand-900">
        <p class="font-black">Money is only ever marked paid by a person.</p>
        <p class="mt-1">Verify a TrxID against your wallet statement first. No customer link can do it.</p>
    </div>
</nav>
