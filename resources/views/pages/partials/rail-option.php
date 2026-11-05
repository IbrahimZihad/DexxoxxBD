<?php

declare(strict_types=1);

/**
 * One payment rail as a selectable card: brand gradient, merchant number and a
 * copy button when the number matters. Used by checkout (pick a method) and by
 * the payment page (send the money), so both always agree with
 * config/payments.php and whatever numbers the admin typed in settings.
 *
 * The radio sits inside its own label, so selection is native (works with
 * JavaScript off) and the highlight is pure CSS via :has().
 *
 * @var array<string, mixed> $rail        Rail card built from PaymentService::available().
 * @var string               $selected    Key of the currently selected rail.
 * @var bool                 $showNumber  Render the merchant number + copy button,
 *                                        and the reference-field hints the payment
 *                                        page swaps between rails with.
 * @var string               $idPrefix
 */
$rail = (array) ($rail ?? []);
$selected = (string) ($selected ?? '');
$showNumber = (bool) ($showNumber ?? false);
$idPrefix = (string) ($idPrefix ?? 'rail');

$key = (string) ($rail['key'] ?? '');
$label = (string) ($rail['label'] ?? $key);
$instructions = (string) ($rail['instructions'] ?? '');
$number = trim((string) ($rail['number'] ?? ''));
$numberLabel = (string) ($rail['number_label'] ?? '');
$color = (string) ($rail['color'] ?? '#6a31e0');
$txnLabel = (string) ($rail['txn_label'] ?? 'Transaction ID');
$txnHint = (string) ($rail['txn_hint'] ?? '');
$requiresTxn = (bool) ($rail['requires_txn'] ?? false);

// A colour from config into a style attribute: pattern-checked, then escaped.
$brand = preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1 ? $color : '#6a31e0';
$isOn = $key !== '' && $key === $selected;
$id = $idPrefix . '-' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower($key));
$stripeStyle = 'background-image:linear-gradient(120deg,' . $brand . ' 0%,' . $brand . ' 45%,#1c0b47 150%)';
?>
<label for="<?= e($id) ?>" class="group has-[:checked]:border-brand-500 has-[:checked]:shadow-glow relative flex cursor-pointer flex-col overflow-hidden rounded-card border-2 border-brand-100 bg-white p-4 text-left transition-all duration-200 hover:-translate-y-0.5">
    <input type="radio"
           id="<?= e($id) ?>"
           name="method"
           value="<?= e($key) ?>"
           class="sr-only"
           <?= e($isOn ? 'checked' : '') ?>
           <?php if ($showNumber): ?>
               data-txn-label="<?= e($txnLabel) ?>"
               data-txn-hint="<?= e($txnHint) ?>"
               data-txn-required="<?= e($requiresTxn ? '1' : '0') ?>"
           <?php endif; ?>>

    <span class="absolute inset-x-0 top-0 h-1.5 opacity-70 transition-opacity duration-200 group-has-[:checked]:opacity-100" style="<?= e($stripeStyle) ?>" aria-hidden="true"></span>

    <span class="mt-1 flex items-start gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-brand text-white shadow-soft" style="<?= e($stripeStyle) ?>" aria-hidden="true">
            <span class="text-base font-black"><?= e(mb_strtoupper(mb_substr($label, 0, 1))) ?></span>
        </span>

        <span class="min-w-0 grow">
            <span class="flex items-center justify-between gap-2">
                <span class="block truncate text-sm font-black text-ink"><?= e($label) ?></span>
                <span class="flex shrink-0 items-center gap-1.5">
                    <span class="hidden rounded-pill bg-brand-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-white group-has-[:checked]:inline-block" aria-hidden="true">Picked</span>
                    <span class="grid h-5 w-5 place-items-center rounded-full border-2 border-brand-200" aria-hidden="true">
                        <span class="h-2.5 w-2.5 scale-0 rounded-full bg-brand-600 transition-transform duration-200 group-has-[:checked]:scale-100"></span>
                    </span>
                </span>
            </span>

            <span class="mt-1 block text-xs leading-relaxed text-ink-400"><?= e($instructions) ?></span>

            <?php if ($showNumber && $number !== ''): ?>
                <span class="mt-3 block rounded-brand bg-brand-50 p-2.5">
                    <span class="block text-[10px] font-black uppercase tracking-widest text-ink-400"><?= e($numberLabel === '' ? 'Send the money to' : $numberLabel) ?></span>
                    <span class="mt-1 flex items-center gap-2">
                        <span data-copy-source class="select-all font-mono text-sm font-black tracking-wide text-ink"><?= e($number) ?></span>
                        <button type="button" data-copy data-copy-value="<?= e($number) ?>"
                                class="ml-auto shrink-0 rounded-pill bg-white px-3 py-1 text-[11px] font-bold text-brand-700 ring-1 ring-brand-200 transition-colors duration-200 hover:bg-brand-100">
                            Copy
                        </button>
                    </span>
                </span>
            <?php endif; ?>

            <?php if ($showNumber): ?>
                <span class="mt-2 block border-t border-dashed border-brand-100 pt-2 text-[11px] leading-relaxed text-ink-400">
                    <?php if ($requiresTxn): ?>
                        We need your <strong class="font-black text-ink"><?= e($txnLabel) ?></strong>.
                        <?= e($txnHint) ?>
                    <?php else: ?>
                        Nothing to paste yet - we confirm the amount with you on WhatsApp before handover.
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </span>
    </span>
</label>
