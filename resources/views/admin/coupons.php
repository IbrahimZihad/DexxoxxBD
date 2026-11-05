<?php

declare(strict_types=1);

/**
 * Admin coupons (owner-only across the board, per routes/web.php). The read view
 * is a real .a-table; a create form and one edit form per row live in
 * [data-accordion] panels.
 *
 * The panels are NOT hidden in the markup on purpose: every form is a plain
 * <form method="post"> with a CSRF token that stays reachable and submittable with
 * JavaScript switched off. used_count is shown read-only (only checkout moves it)
 * and never posted. The copy button is a [data-copy] control app.js wires up; with
 * JS off the code is still plainly visible in the cell.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $coupons
 */
$brandName = setting('brand.name', 'DexxozzBD');

$coupons = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($coupons ?? [])));

$input = 'w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300';
$labelClass = 'block text-xs font-black uppercase tracking-[0.12em] text-ink-400';
$box = 'mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-600';

/** DB "Y-m-d H:i:s" (or null) -> a value a datetime-local input understands. */
$toLocal = static function (mixed $stored): string {
    $stored = trim((string) ($stored ?? ''));

    if ($stored === '') {
        return '';
    }

    $moment = date_create($stored);

    return $moment === false ? '' : $moment->format('Y-m-d\TH:i');
};

$view->extend('layouts/admin');
$view->setSection('title', 'Coupons - ' . $brandName);
$view->setSection('page_title', 'Coupons');
$view->setSection('page_actions', '<a href="' . e(route('admin.services')) . '" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M4 7h16v12H4z" stroke-linejoin="round"/><path d="M9 7V4h6v3" stroke-linejoin="round"/></svg>'
    . 'Manage services</a>');
?>

<section aria-labelledby="coupons-add" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div data-accordion>
        <button type="button" data-accordion-btn aria-expanded="true" class="flex w-full items-center justify-between gap-3 text-left">
            <span>
                <span id="coupons-add" class="text-base font-black tracking-tight text-ink">Add a coupon</span>
                <span class="mt-0.5 block text-xs text-ink-400">A discount code shoppers can apply at checkout - a percentage or a fixed taka amount.</span>
            </span>
            <span class="rounded-brand bg-brand-600 px-3 py-1.5 text-xs font-black text-white">Open form</span>
        </button>

        <div data-accordion-panel class="mt-4">
            <form method="post" action="<?= e(route('admin.coupon.store')) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Code <span class="text-rose-500">*</span></span>
                        <input type="text" name="code" value="<?= e(old('code')) ?>" required maxlength="32" class="<?= e($input) ?> mt-1 <?= e(error_class('code')) ?> font-mono uppercase" placeholder="DIWALI25">
                        <?php foreach (field_errors('code') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                        <span class="mt-1 block text-xs text-ink-400">Letters, numbers, dashes and underscores. Stored uppercase.</span>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Type <span class="text-rose-500">*</span></span>
                        <select name="type" required class="<?= e($input) ?> mt-1">
                            <?php foreach (['percent' => 'Percentage', 'fixed' => 'Fixed taka'] as $typeValue => $typeLabel): ?>
                                <option value="<?= e($typeValue) ?>" <?= e(old('type', 'percent') === $typeValue ? 'selected' : '') ?>><?= e($typeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php foreach (field_errors('type') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Value <span class="text-rose-500">*</span></span>
                        <input type="number" name="value" value="<?= e(old('value')) ?>" required min="0" step="0.01" inputmode="decimal" class="<?= e($input) ?> mt-1 <?= e(error_class('value')) ?>" placeholder="25">
                        <?php foreach (field_errors('value') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                        <span class="mt-1 block text-xs text-ink-400">Percent (0-100) or taka, depending on the type.</span>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Minimum order (&#2547;)</span>
                        <input type="number" name="min_order" value="<?= e(old('min_order', '0')) ?>" min="0" step="0.01" inputmode="decimal" class="<?= e($input) ?> mt-1">
                        <span class="mt-1 block text-xs text-ink-400">0 = no basket minimum.</span>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Max uses</span>
                        <input type="number" name="max_uses" value="<?= e(old('max_uses', '0')) ?>" min="0" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                        <span class="mt-1 block text-xs text-ink-400">0 = unlimited redemptions.</span>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Per-customer limit</span>
                        <input type="number" name="per_customer_limit" value="<?= e(old('per_customer_limit', '1')) ?>" min="0" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                        <span class="mt-1 block text-xs text-ink-400">0 = a shopper can reuse it freely.</span>
                    </label>

                    <label class="block sm:col-span-2 xl:col-span-3">
                        <span class="<?= e($labelClass) ?>">Expires at</span>
                        <input type="datetime-local" name="expires_at" value="<?= e(old('expires_at')) ?>" class="<?= e($input) ?> mt-1 <?= e(error_class('expires_at')) ?>">
                        <?php foreach (field_errors('expires_at') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                        <span class="mt-1 block text-xs text-ink-400">Leave blank for a code that never expires.</span>
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                        <input type="checkbox" name="is_active" value="1" checked class="<?= e($box) ?>"> Active
                    </label>
                    <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-5 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Add coupon</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php if ($coupons === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => 'No coupons yet',
            'text' => 'Create a code above and shoppers can start saving at checkout.',
            'icon' => 'bag',
        ]) ?>
    </div>
<?php else: ?>
    <div data-accordion class="mt-5 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="a-table">
                <caption class="sr-only">Coupons with their discount, usage, expiry and status</caption>
                <thead>
                    <tr>
                        <th scope="col">Code</th>
                        <th scope="col">Discount</th>
                        <th scope="col">Min order</th>
                        <th scope="col">Used</th>
                        <th scope="col">Per customer</th>
                        <th scope="col">Expires</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon): ?>
                        <?php
                        $cid = (int) ($coupon['id'] ?? 0);
                        $code = strtoupper(trim((string) ($coupon['code'] ?? '')));
                        $type = (string) ($coupon['type'] ?? 'percent');
                        $value = (float) ($coupon['value'] ?? 0);
                        $active = (int) ($coupon['is_active'] ?? 0) === 1;
                        $used = (int) ($coupon['used_count'] ?? 0);
                        $maxUses = (int) ($coupon['max_uses'] ?? 0);
                        $perCustomer = (int) ($coupon['per_customer_limit'] ?? 0);
                        $minOrder = (float) ($coupon['min_order'] ?? 0);
                        $expiresRaw = $coupon['expires_at'] ?? null;
                        $expiresStamp = $expiresRaw === null ? false : strtotime((string) $expiresRaw);
                        $expired = $expiresStamp !== false && $expiresStamp < time();
                        ?>
                        <tr>
                            <td>
                                <span class="flex items-center gap-2">
                                    <span class="rounded-brand bg-brand-50 px-2 py-1 font-mono text-sm font-black tracking-wide text-brand-800"><?= e($code) ?></span>
                                    <button type="button" data-copy data-copy-value="<?= e($code) ?>" class="shrink-0 rounded-brand border border-brand-200 bg-white p-1.5 text-ink-400 transition-colors duration-200 hover:border-brand-300 hover:text-brand-700" aria-label="Copy code <?= e($code) ?>">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10" stroke-linecap="round"/></svg>
                                    </button>
                                </span>
                            </td>
                            <td class="whitespace-nowrap font-black text-ink">
                                <?= $type === 'percent' ? e(rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.')) . '% off' : e(taka($value)) ?>
                            </td>
                            <td class="whitespace-nowrap"><?= $minOrder > 0 ? e(taka($minOrder)) : '<span class="text-ink-400">None</span>' ?></td>
                            <td class="whitespace-nowrap">
                                <span class="<?= e($maxUses > 0 && $used >= $maxUses ? 'font-black text-rose-700' : 'font-bold text-ink') ?>"><?= e((string) $used) ?></span>
                                <span class="text-ink-400">/ <?= $maxUses > 0 ? e((string) $maxUses) : 'unlimited' ?></span>
                            </td>
                            <td class="whitespace-nowrap"><?= $perCustomer > 0 ? e((string) $perCustomer) : '<span class="text-ink-400">Unlimited</span>' ?></td>
                            <td class="whitespace-nowrap">
                                <?php if ($expiresStamp === false): ?>
                                    <span class="text-ink-400">Never</span>
                                <?php elseif ($expired): ?>
                                    <span class="rounded-pill bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-800">Expired</span>
                                    <span class="mt-0.5 block text-xs text-ink-400"><?= e(date('d M Y H:i', $expiresStamp)) ?></span>
                                <?php else: ?>
                                    <span class="font-bold text-ink"><?= e(date('d M Y', $expiresStamp)) ?></span>
                                    <span class="block text-xs text-ink-400"><?= e(date('H:i', $expiresStamp)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?= e($active ? 'rounded-pill bg-emerald-100 px-2 py-0.5 text-xs font-black text-emerald-800' : 'rounded-pill bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-800') ?>"><?= $active ? 'Active' : 'Paused' ?></span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" data-accordion-btn aria-expanded="true" class="rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">Edit / hide</button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="8" data-accordion-panel class="bg-brand-50/40">
                                <form method="post" action="<?= e(route('admin.coupon.update', ['id' => $cid])) ?>" class="space-y-4 py-2">
                                    <?= csrf_field() ?>
                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Code</span>
                                            <input type="text" name="code" value="<?= e($code) ?>" required maxlength="32" class="<?= e($input) ?> mt-1 font-mono uppercase">
                                            <span class="mt-1 block text-xs text-ink-400">Renaming points future orders at the new code.</span>
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Type</span>
                                            <select name="type" required class="<?= e($input) ?> mt-1">
                                                <?php foreach (['percent' => 'Percentage', 'fixed' => 'Fixed taka'] as $typeValue => $typeLabel): ?>
                                                    <option value="<?= e($typeValue) ?>" <?= e($type === $typeValue ? 'selected' : '') ?>><?= e($typeLabel) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Value</span>
                                            <input type="number" name="value" value="<?= e(number_format($value, 2, '.', '')) ?>" required min="0" step="0.01" inputmode="decimal" class="<?= e($input) ?> mt-1">
                                            <span class="mt-1 block text-xs text-ink-400">Percent (0-100) or taka.</span>
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Minimum order (&#2547;)</span>
                                            <input type="number" name="min_order" value="<?= e(number_format($minOrder, 2, '.', '')) ?>" min="0" step="0.01" inputmode="decimal" class="<?= e($input) ?> mt-1">
                                            <span class="mt-1 block text-xs text-ink-400">0 = no basket minimum.</span>
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Max uses</span>
                                            <input type="number" name="max_uses" value="<?= e((string) $maxUses) ?>" min="0" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                                            <span class="mt-1 block text-xs text-ink-400">0 = unlimited. <?= e((string) $used) ?> use(s) already recorded.</span>
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Per-customer limit</span>
                                            <input type="number" name="per_customer_limit" value="<?= e((string) $perCustomer) ?>" min="0" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                                            <span class="mt-1 block text-xs text-ink-400">0 = a shopper can reuse it freely.</span>
                                        </label>

                                        <label class="block sm:col-span-2 xl:col-span-3">
                                            <span class="<?= e($labelClass) ?>">Expires at</span>
                                            <input type="datetime-local" name="expires_at" value="<?= e($toLocal($expiresRaw)) ?>" class="<?= e($input) ?> mt-1 <?= e($expired ? 'border-rose-300 ring-2 ring-rose-400' : '') ?>">
                                            <span class="mt-1 block text-xs text-ink-400"><?= $expired ? 'Currently expired - set a future date (or leave blank) and mark it active to revive it.' : 'Leave blank for a code that never expires.' ?></span>
                                        </label>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                                            <input type="checkbox" name="is_active" value="1" <?= e($active ? 'checked' : '') ?> class="<?= e($box) ?>"> Active
                                        </label>
                                        <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-4 py-2 text-sm font-black text-white transition-colors duration-200 hover:bg-brand-700">Save changes</button>
                                    </div>
                                </form>

                                <form method="post" action="<?= e(route('admin.coupon.delete', ['id' => $cid])) ?>" class="mt-3 rounded-brand border border-rose-200 bg-rose-50/70 p-3">
                                    <?= csrf_field() ?>
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <label class="flex cursor-pointer items-start gap-2 text-xs font-semibold text-rose-900">
                                            <input type="checkbox" name="confirm" value="1" required class="<?= e($box) ?> accent-rose-600">
                                            <span>Delete <strong><?= e($code) ?></strong> permanently after <?= e((string) $used) ?> recorded use(s). Past orders keep the code text, but this cannot be undone.</span>
                                        </label>
                                        <button type="submit" class="rounded-brand bg-rose-600 px-4 py-2 text-xs font-black text-white transition-colors duration-200 hover:bg-rose-700">Delete coupon</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
