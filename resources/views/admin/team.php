<?php

declare(strict_types=1);

/**
 * Team management (administrators and staff). A create form lives in the first
 * [data-accordion] group; each account row carries one [data-accordion-btn] and
 * one [data-accordion-panel] in table order inside a single [data-accordion]
 * group. Panels are left visible so every form works with JavaScript off.
 *
 * A password is only ever typed into a password field - never echoed back, never
 * placed in a hidden value. The account you are signed in as cannot be deleted or
 * deactivated from here, and the last active administrator is called out.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $staff
 * @var int $currentUserId
 * @var int $adminCount
 * @var list<string> $roles
 */
$brandName = setting('brand.name', 'ZapSub');

$staff = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($staff ?? [])));
$currentUserId = (int) ($currentUserId ?? 0);
$adminCount = max(0, (int) ($adminCount ?? 0));
$roles = array_values(array_map('strval', (array) ($roles ?? [])));

$input = 'w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300';
$labelClass = 'block text-xs font-black uppercase tracking-[0.12em] text-ink-400';
$box = 'mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-600';

$rolePill = static fn (string $role): string => match ($role) {
    'admin' => 'rounded-pill bg-brand-600 px-2.5 py-1 text-xs font-black text-white',
    'staff' => 'rounded-pill bg-accent-100 px-2.5 py-1 text-xs font-black text-accent-800',
    default => 'rounded-pill bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-700',
};

$view->extend('layouts/admin');
$view->setSection('title', 'Team - ' . $brandName);
$view->setSection('page_title', 'Team');
$view->setSection('page_actions', '<a href="' . e(route('admin.audit')) . '" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 3h9l3 3v15H6z" stroke-linejoin="round"/><path d="m9 13 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    . 'Audit trail</a>');
?>

<?php if ($adminCount <= 1): ?>
    <div role="alert" class="mb-5 flex items-start gap-3 rounded-brand border-2 border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M12 3 4 6v6c0 4.4 3.2 8.2 8 9 4.8-.8 8-4.6 8-9V6l-8-3Z" stroke-linejoin="round"/><path d="M12 9v4M12 16h.01" stroke-linecap="round"/>
        </svg>
        <p>
            <strong class="font-black">Only one active administrator is left.</strong>
            The system will refuse to delete, deactivate or demote this account so the shop can never lose
            its last owner. Add and activate a second admin first if you want to hand over.
        </p>
    </div>
<?php endif; ?>

<section aria-labelledby="team-add" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div data-accordion>
        <button type="button" data-accordion-btn aria-expanded="true" class="flex w-full items-center justify-between gap-3 text-left">
            <span>
                <span id="team-add" class="text-base font-black tracking-tight text-ink">Add a team member</span>
                <span class="mt-0.5 block text-xs text-ink-400">Give someone a staff or admin login. This list shows administrators and staff.</span>
            </span>
            <span class="rounded-brand bg-brand-600 px-3 py-1.5 text-xs font-black text-white">Open form</span>
        </button>

        <div data-accordion-panel class="mt-4">
            <form method="post" action="<?= e(route('admin.team.store')) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Name <span class="text-rose-500">*</span></span>
                        <input type="text" name="name" value="<?= e(old('name')) ?>" required maxlength="120" class="<?= e($input) ?> mt-1" placeholder="Rafi Ahmed">
                        <?php foreach (field_errors('name') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Email <span class="text-rose-500">*</span></span>
                        <input type="email" name="email" value="<?= e(old('email')) ?>" required maxlength="180" autocomplete="off" spellcheck="false" class="<?= e($input) ?> mt-1" placeholder="person@zapsub.test">
                        <?php foreach (field_errors('email') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Phone</span>
                        <input type="tel" name="phone" value="<?= e(old('phone')) ?>" maxlength="20" class="<?= e($input) ?> mt-1" placeholder="01712-345678">
                        <?php foreach (field_errors('phone') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Role <span class="text-rose-500">*</span></span>
                        <select name="role" required class="<?= e($input) ?> mt-1">
                            <?php $currentRole = old('role', 'staff'); ?>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= e($role) ?>" <?= e($role === $currentRole ? 'selected' : '') ?>><?= e(status_label($role)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="mt-1 block text-xs text-ink-400">Admins reach settings, team and coupons. Staff work orders.</span>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Password <span class="text-rose-500">*</span></span>
                        <input type="password" name="password" required minlength="8" maxlength="200" autocomplete="new-password" class="<?= e($input) ?> mt-1 <?= e(field_errors('password') !== [] ? 'border-rose-300 ring-2 ring-rose-400' : '') ?>">
                        <?php foreach (field_errors('password') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Confirm password <span class="text-rose-500">*</span></span>
                        <input type="password" name="password_confirmation" required minlength="8" maxlength="200" autocomplete="new-password" class="<?= e($input) ?> mt-1">
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                        <input type="checkbox" name="is_active" value="1" checked class="<?= e($box) ?>"> Account is active
                    </label>
                    <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-5 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Add member</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php if ($staff === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => 'No team members yet',
            'text' => 'Add an administrator or staff account above to get started.',
            'icon' => 'check',
        ]) ?>
    </div>
<?php else: ?>
    <div data-accordion class="mt-5 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
        <div class="border-b border-brand-100 px-4 py-3 text-xs font-bold text-ink-400"><?= e((string) count($staff)) ?> account(s) with panel access</div>

        <div class="overflow-x-auto">
            <table class="a-table">
                <caption class="sr-only">Team accounts with their role and status</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Last sign-in</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $member): ?>
                        <?php
                        $uid = (int) ($member['id'] ?? 0);
                        $role = (string) ($member['role'] ?? 'staff');
                        $active = (int) ($member['is_active'] ?? 0) === 1;
                        $isSelf = $uid === $currentUserId;
                        $lastLogin = $member['last_login_at'] ?? null;
                        ?>
                        <tr>
                            <td>
                                <span class="block font-black text-ink"><?= e((string) ($member['name'] ?? '')) ?></span>
                                <?php if ($isSelf): ?><span class="rounded-pill bg-brand-100 px-2 py-0.5 text-[0.65rem] font-black text-brand-700">That&rsquo;s you</span><?php endif; ?>
                            </td>
                            <td>
                                <span class="block max-w-[15rem] truncate text-sm font-semibold text-ink"><?= e((string) ($member['email'] ?? '')) ?></span>
                                <span class="block font-mono text-xs text-ink-400"><?= e((string) ($member['phone'] ?? '')) ?></span>
                            </td>
                            <td><span class="<?= e($rolePill($role)) ?>"><?= e(status_label($role)) ?></span></td>
                            <td><span class="<?= e($active ? 'rounded-pill bg-emerald-100 px-2 py-0.5 text-xs font-black text-emerald-800' : 'rounded-pill bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-800') ?>"><?= $active ? 'Active' : 'Disabled' ?></span></td>
                            <td class="whitespace-nowrap text-xs font-semibold text-ink-400"><?= e($lastLogin === null ? 'Never' : time_ago((string) $lastLogin)) ?></td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" data-accordion-btn aria-expanded="true" class="rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">Edit</button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6" data-accordion-panel class="bg-brand-50/40">
                                <form method="post" action="<?= e(route('admin.team.update', ['id' => $uid])) ?>" class="space-y-4 py-2">
                                    <?= csrf_field() ?>
                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Name</span>
                                            <input type="text" name="name" value="<?= e((string) ($member['name'] ?? '')) ?>" required maxlength="120" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Email</span>
                                            <input type="email" name="email" value="<?= e((string) ($member['email'] ?? '')) ?>" required maxlength="180" autocomplete="off" spellcheck="false" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Phone</span>
                                            <input type="tel" name="phone" value="<?= e((string) ($member['phone'] ?? '')) ?>" maxlength="20" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Role</span>
                                            <select name="role" required class="<?= e($input) ?> mt-1">
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?= e($role) ?>" <?= e($role === $current ? 'selected' : '') ?>><?= e(status_label($role)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">New password</span>
                                            <input type="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" class="<?= e($input) ?> mt-1" placeholder="Leave blank to keep it">
                                            <?php foreach (field_errors('password') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Confirm new password</span>
                                            <input type="password" name="password_confirmation" minlength="8" maxlength="200" autocomplete="new-password" class="<?= e($input) ?> mt-1">
                                        </label>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                                        <?php if ($isSelf): ?>
                                            <input type="hidden" name="is_active" value="1">
                                            <span class="text-sm font-bold text-ink-400">Your own account stays active &mdash; only another admin can change that.</span>
                                        <?php else: ?>
                                            <input type="hidden" name="is_active" value="0">
                                            <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                                                <input type="checkbox" name="is_active" value="1" <?= e($active ? 'checked' : '') ?> class="<?= e($box) ?>"> Account is active
                                            </label>
                                        <?php endif; ?>
                                        <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-4 py-2 text-sm font-black text-white transition-colors duration-200 hover:bg-brand-700">Save changes</button>
                                    </div>
                                </form>

                                <?php if ($isSelf): ?>
                                    <p class="mt-3 rounded-brand bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-800">
                                        You cannot delete the account you are signed in with.
                                    </p>
                                <?php else: ?>
                                    <form method="post" action="<?= e(route('admin.team.delete', ['id' => $uid])) ?>" class="mt-3 rounded-brand border border-rose-200 bg-rose-50/70 p-3">
                                        <?= csrf_field() ?>
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <label class="flex cursor-pointer items-start gap-2 text-xs font-semibold text-rose-900">
                                                <input type="checkbox" name="confirm" value="1" required class="<?= e($box) ?> accent-rose-600">
                                                <span>Delete <strong><?= e((string) ($member['name'] ?? '')) ?></strong> (<?= e((string) ($member['email'] ?? '')) ?>) permanently. Their past orders stay, but this login is gone.</span>
                                            </label>
                                            <button type="submit" class="rounded-brand bg-rose-600 px-4 py-2 text-xs font-black text-white transition-colors duration-200 hover:bg-rose-700">Delete account</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
