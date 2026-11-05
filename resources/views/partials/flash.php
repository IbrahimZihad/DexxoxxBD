<?php

declare(strict_types=1);

/**
 * Toasts seeded straight into the toast host (so a page reload from a normal
 * form post still shows feedback) plus the validation error summary.
 *
 * Session::pullFlash() returns type => list<string>; the ['type','message'] list
 * shape is accepted too so the partial cannot render an empty bubble.
 */
$toasts = [];

foreach ((array) ($flash ?? []) as $type => $entry) {
    if (is_array($entry)) {
        if (array_key_exists('message', $entry)) {
            $toasts[] = [(string) ($entry['type'] ?? 'info'), (string) $entry['message']];
        } else {
            foreach ($entry as $text) {
                if (is_scalar($text)) {
                    $toasts[] = [(string) $type, trim((string) $text)];
                }
            }
        }
    } elseif (is_scalar($entry)) {
        $toasts[] = [(string) $type, trim((string) $entry)];
    }
}

$toasts = array_values(array_filter($toasts, static fn (array $toast): bool => $toast[1] !== ''));

$allErrors = array_merge(...array_map(
    static fn (mixed $messages): array => array_values((array) $messages),
    (array) ($errors ?? [])
) ?: [[]]);

$toneFor = static fn (string $type): string => match ($type) {
    'success', 'ok' => 'bg-emerald-600',
    'error', 'danger' => 'bg-rose-600',
    'warning' => 'bg-amber-500',
    default => 'bg-brand-600',
};
?>
<?php if ($toasts !== []): ?>
    <div data-toast-host aria-live="polite" class="fixed inset-x-0 top-4 z-[9999] flex flex-col items-center gap-2 px-4 pointer-events-none">
        <?php foreach ($toasts as $index => [$type, $message]): ?>
            <?php /* A checkbox + label pair gives a real dismiss control without any script. */ ?>
            <input type="checkbox" id="flash-<?= e((string) ($index + 1)) ?>" class="peer sr-only">
            <div class="animate-pop pointer-events-auto flex max-w-md items-start gap-3 rounded-brand px-4 py-3 text-sm font-semibold text-white shadow-card <?= e($toneFor($type)) ?> peer-checked:hidden">
                <span class="flex-1"><?= e($message) ?></span>
                <label for="flash-<?= e((string) ($index + 1)) ?>" class="cursor-pointer select-none text-lg leading-none text-white/70 hover:text-white" title="Dismiss">&times;</label>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (($errors ?? []) !== [] && $allErrors !== []): ?>
    <div class="mx-auto -mt-2 max-w-7xl px-4 pt-4 lg:px-6">
        <div role="alert" class="reveal is-revealed rounded-brand border-2 border-rose-300 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="flex items-center gap-2 font-bold">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01" stroke-linecap="round"/>
                </svg>
                Please check the highlighted fields.
            </p>
            <ul class="mt-2 list-disc space-y-1 ps-8">
                <?php foreach (array_slice($allErrors, 0, 8) as $error): ?>
                    <li><?= e((string) $error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
