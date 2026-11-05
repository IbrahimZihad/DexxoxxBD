<?php

declare(strict_types=1);

/**
 * Contact inbox. The status row above the list is a set of real GET links, so a
 * filter works with JavaScript off and can be pasted into a chat. Each enquiry
 * carries one [data-accordion-btn] and one [data-accordion-panel] in table order
 * (the shipped accordion pairs them by index and opens one at a time); the panel
 * is left visible in the markup so the reply form is reachable without script.
 *
 * The message body and any phone number are customer data: printed for staff and
 * escaped with e(), never placed into a URL.
 *
 * @var App\Core\View $view
 * @var array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $paging
 * @var string $status
 * @var list<string> $statuses
 * @var int $unread
 */
$brandName = setting('brand.name', 'ZapSub');

$paging = (array) ($paging ?? []);
$rows = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($paging['items'] ?? [])));
$total = (int) ($paging['total'] ?? 0);
$pages = max(1, (int) ($paging['pages'] ?? 1));
$page = max(1, (int) ($paging['page'] ?? 1));
$perPage = max(1, (int) ($paging['per_page'] ?? 25));

$status = trim((string) ($status ?? ''));
$statuses = array_values((array) ($statuses ?? []));
$unread = max(0, (int) ($unread ?? 0));

$labels = ['new' => 'New', 'answered' => 'Answered', 'archived' => 'Archived'];
$toneFor = static fn (string $value): string => match ($value) {
    'new' => 'bg-brand-100 text-brand-800 ring-1 ring-brand-200',
    'answered' => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
    'archived' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
};

$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);

/** Rebuild /admin/messages?status=…&page=… keeping the active filter. */
$messagesUrl = static function (int $target) use ($status): string {
    $query = $status === '' ? [] : ['status' => $status];

    if ($target > 1) {
        $query['page'] = $target;
    }

    return route('admin.messages', [], $query);
};

$view->extend('layouts/admin');
$view->setSection('title', 'Messages - ' . $brandName);
$view->setSection('page_title', 'Messages');
$view->setSection('page_actions', '<a href="' . e(route('admin.dashboard')) . '" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M4 13h4l1.5 3h5L16 13h4" stroke-linejoin="round"/><path d="M4 13 6 5h12l2 8v6H4v-6Z" stroke-linejoin="round"/></svg>'
    . 'Dashboard</a>');
?>

<div class="no-scrollbar flex gap-2 overflow-x-auto rounded-card border border-brand-100 bg-white p-2 shadow-soft">
    <a href="<?= e(route('admin.messages')) ?>" class="<?= e($status === '' ? 'bg-brand-600 text-white' : 'text-ink-700 hover:bg-brand-50') ?> whitespace-nowrap rounded-brand px-4 py-2 text-sm font-bold transition-colors duration-200">All enquiries</a>
    <?php foreach ($statuses as $state): ?>
        <a href="<?= e(route('admin.messages', [], ['status' => $state])) ?>" class="<?= e($status === $state ? 'bg-brand-600 text-white' : 'text-ink-700 hover:bg-brand-50') ?> inline-flex items-center gap-2 whitespace-nowrap rounded-brand px-4 py-2 text-sm font-bold transition-colors duration-200">
            <?= e($labels[$state] ?? status_label($state)) ?>
            <?php if ($state === 'new' && $unread > 0): ?>
                <span class="<?= e($status === 'new' ? 'bg-white/25 text-white' : 'bg-brand-100 text-brand-800') ?> rounded-pill px-2 py-0.5 text-xs font-black"><?= e((string) $unread) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($rows === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => $status === '' ? 'The inbox is empty' : 'Nothing in ' . ($labels[$status] ?? $status),
            'text' => $status === ''
                ? 'Enquiries from the contact form, the chat head and WhatsApp all land here.'
                : 'Change the filter above to see the rest of the queue.',
            'action_url' => $status === '' ? null : route('admin.messages'),
            'action_label' => $status === '' ? null : 'All enquiries',
            'icon' => 'inbox',
        ]) ?>
    </div>
<?php else: ?>
    <div data-accordion class="mt-5 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-brand-100 px-4 py-3 text-xs font-bold text-ink-400">
            <p>Showing <?= e((string) $from) ?>-&nbsp;<?= e((string) $to) ?> of <?= e(number_format($total)) ?></p>
            <p>Newest first</p>
        </div>

        <div class="overflow-x-auto">
            <table class="a-table">
                <caption class="sr-only">Contact enquiries with their channel, age and status</caption>
                <thead>
                    <tr>
                        <th scope="col">From</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Channel</th>
                        <th scope="col">Received</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $message): ?>
                        <?php
                        $mid = (int) ($message['id'] ?? 0);
                        $rowStatus = (string) ($message['status'] ?? 'new');
                        $subject = trim((string) ($message['subject'] ?? ''));
                        $email = trim((string) ($message['email'] ?? ''));
                        $phone = trim((string) ($message['phone'] ?? ''));
                        $body = (string) ($message['message'] ?? '');
                        $replyNote = trim((string) ($message['reply_note'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <span class="block font-black text-ink"><?= e((string) ($message['name'] ?? '')) ?></span>
                                <?php if ($email !== ''): ?>
                                    <span class="block max-w-[16rem] truncate text-xs text-ink-400"><?= e($email) ?></span>
                                <?php endif; ?>
                                <?php if ($phone !== ''): ?>
                                    <span class="block font-mono text-xs text-ink-400"><?= e($phone) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="block max-w-[22rem] font-bold text-ink"><?= e($subject === '' ? short_text($body, 60) : $subject) ?></span>
                                <?php if ($subject !== ''): ?>
                                    <span class="block max-w-[22rem] truncate text-xs text-ink-400"><?= e(short_text($body, 90)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="rounded-pill bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-800"><?= e(status_label((string) ($message['channel'] ?? 'form'))) ?></span></td>
                            <td class="whitespace-nowrap text-xs font-semibold text-ink-400" title="<?= e((string) ($message['created_at'] ?? '')) ?>"><?= e(time_ago((string) ($message['created_at'] ?? 'now'))) ?></td>
                            <td><span class="inline-flex items-center rounded-pill px-2.5 py-1 text-xs font-bold <?= e($toneFor($rowStatus)) ?>"><?= e($labels[$rowStatus] ?? status_label($rowStatus)) ?></span></td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" data-accordion-btn aria-expanded="true" class="rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">Read / reply</button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6" data-accordion-panel class="bg-brand-50/40">
                                <div class="space-y-4 py-2">
                                    <div>
                                        <p class="text-xs font-black uppercase tracking-[0.12em] text-ink-400">Full message</p>
                                        <div class="mt-1 whitespace-pre-line rounded-brand border border-brand-100 bg-white p-3 text-sm leading-relaxed text-ink"><?= e($body) ?></div>
                                    </div>

                                    <?php if ($replyNote !== ''): ?>
                                        <div class="rounded-brand border border-emerald-200 bg-emerald-50/70 p-3">
                                            <p class="text-xs font-black uppercase tracking-[0.12em] text-emerald-700">Reply note on file</p>
                                            <p class="mt-1 whitespace-pre-line text-sm text-emerald-900"><?= e($replyNote) ?></p>
                                            <?php if (($message['answered_at'] ?? null) !== null): ?>
                                                <p class="mt-1 text-xs font-semibold text-emerald-700">Answered <?= e(time_ago((string) $message['answered_at'])) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <form method="post" action="<?= e(route('admin.message.answer', ['id' => $mid])) ?>" class="space-y-3 rounded-brand border border-brand-100 bg-white p-3">
                                        <?= csrf_field() ?>
                                        <?php /* Also posted so the redirect can keep the filter when no Referer is available. */ ?>
                                        <input type="hidden" name="status" value="<?= e($status) ?>">
                                        <label class="block">
                                            <span class="block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Reply note <span class="text-rose-500">*</span></span>
                                            <textarea name="reply_note" rows="3" required maxlength="500" class="mt-1 w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300 resize-y" placeholder="What you told them, or the order the note refers to."><?= e(old('reply_note')) ?></textarea>
                                            <?php foreach (field_errors('reply_note') as $err): ?>
                                                <p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $err) ?></p>
                                            <?php endforeach; ?>
                                        </label>
                                        <p class="text-xs text-ink-400">Saved against this enquiry. The customer is answered on WhatsApp or by phone directly - there is no send button here on purpose.</p>
                                        <button type="submit" class="rounded-brand bg-brand-600 px-5 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Mark answered &amp; save note</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <?php $windowStart = max(1, min($page - 2, $pages - 4)); ?>
        <?php $windowEnd = min($pages, $windowStart + 4); ?>
        <nav aria-label="Message pages" class="mt-6 flex flex-wrap items-center justify-center gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= e($messagesUrl($page - 1)) ?>" rel="prev" class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">Previous</a>
            <?php endif; ?>

            <?php if ($windowStart > 1): ?>
                <a href="<?= e($messagesUrl(1)) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700">1</a>
                <?php if ($windowStart > 2): ?><span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($number = $windowStart; $number <= $windowEnd; $number++): ?>
                <?php if ($number === $page): ?>
                    <span aria-current="page" class="rounded-pill bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2 text-sm font-black text-white shadow-pop"><?= e((string) $number) ?></span>
                <?php else: ?>
                    <a href="<?= e($messagesUrl($number)) ?>" class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50"><?= e((string) $number) ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($windowEnd < $pages): ?>
                <?php if ($windowEnd < $pages - 1): ?><span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span><?php endif; ?>
                <a href="<?= e($messagesUrl($pages)) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700"><?= e((string) $pages) ?></a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
                <a href="<?= e($messagesUrl($page + 1)) ?>" rel="next" class="rounded-pill bg-ink px-4 py-2 text-sm font-bold text-white transition-colors duration-200 hover:bg-brand-700">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
