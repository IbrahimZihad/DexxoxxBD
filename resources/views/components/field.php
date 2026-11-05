<?php

declare(strict_types=1);

/**
 * The form primitive every screen builds on.
 *
 * Data: name, label, type (text|email|tel|password|number|textarea|select|checkbox),
 * value, placeholder, required, hint, options, rows, autocomplete, inputmode.
 * A null value falls back to old(name), so a failed submit refills itself.
 *
 * @var mixed $name
 * @var mixed $label
 * @var mixed $type
 * @var mixed $value
 * @var mixed $placeholder
 * @var mixed $required
 * @var mixed $hint
 * @var array<string|int, string|int>|null $options
 * @var mixed $rows
 * @var mixed $autocomplete
 * @var mixed $inputmode
 */
$name = (string) ($name ?? '');
$label = (string) ($label ?? $name);
$type = in_array((string) ($type ?? ''), ['text', 'email', 'tel', 'password', 'number', 'textarea', 'select', 'checkbox', 'date', 'url'], true) ? (string) $type : 'text';
$value = ($value ?? null) === null ? old($name) : $value;
$placeholder = (string) ($placeholder ?? '');
$required = (bool) ($required ?? false);
$hint = (string) ($hint ?? '');
$options = (array) ($options ?? []);
$rows = max(2, (int) ($rows ?? 4));
$inputmode = (string) ($inputmode ?? ($type === 'number' || $type === 'tel' ? 'numeric' : ''));
$messages = field_errors($name);
$invalid = $messages !== [];

// ids cannot carry the brackets a array-style field name uses (items[0][plan]).
$id = ($name === '' ? 'field' : (preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name) ?? 'field')) . '-' . substr(md5($name), 0, 6);

if ($autocomplete === null || $autocomplete === '') {
    $autocomplete = match ($type) {
        'email' => 'email',
        'password' => 'current-password',
        'tel' => 'tel',
        default => '',
    };
} else {
    $autocomplete = (string) $autocomplete;
}

$control = 'w-full rounded-brand border bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:ring-2';
$control .= $invalid
    ? ' border-rose-300 ring-2 ring-rose-200 ' . error_class($name)
    : ' border-brand-200 focus:border-brand-400 focus:ring-brand-300';
?>
<div class="space-y-1.5">
    <?php if ($type !== 'checkbox' && $label !== ''): ?>
        <label for="<?= e($id) ?>" class="block text-sm font-semibold text-ink">
            <?= e($label) ?>
            <?php if ($required): ?>
                <span class="text-rose-500" aria-hidden="true">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <?php if ($type === 'textarea'): ?>
        <textarea
            id="<?= e($id) ?>"
            name="<?= e($name) ?>"
            rows="<?= e((string) $rows) ?>"
            class="<?= e($control) ?> resize-y"
            placeholder="<?= e($placeholder) ?>"
            <?php if ($required): ?> required<?php endif; ?>
            <?php if ($autocomplete !== ''): ?> autocomplete="<?= e($autocomplete) ?>"<?php endif; ?>
            <?php if ($invalid): ?> aria-invalid="true"<?php endif; ?>
        ><?= e((string) $value) ?></textarea>
    <?php elseif ($type === 'select'): ?>
        <?php $current = (string) $value; ?>
        <select
            id="<?= e($id) ?>"
            name="<?= e($name) ?>"
            class="<?= e($control) ?>"
            <?php if ($required): ?> required<?php endif; ?>
            <?php if ($invalid): ?> aria-invalid="true"<?php endif; ?>
        >
            <?php if (!array_key_exists('', $options)): ?>
                <option value=""><?= e($placeholder === '' ? 'Choose one' : $placeholder) ?></option>
            <?php endif; ?>
            <?php foreach ($options as $optionValue => $optionLabel): ?>
                <option value="<?= e((string) $optionValue) ?>" <?= e((string) $optionValue === $current ? 'selected' : '') ?>>
                    <?= e((string) $optionLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($type === 'checkbox'): ?>
        <label for="<?= e($id) ?>" class="flex cursor-pointer items-start gap-3 rounded-brand border border-brand-100 bg-brand-50/50 p-3">
            <input
                type="checkbox"
                id="<?= e($id) ?>"
                name="<?= e($name) ?>"
                value="<?= e((string) ($value === '' || $value === null ? '1' : $value)) ?>"
                class="mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-600"
                <?php if ($required): ?> required<?php endif; ?>
                <?php if ($invalid): ?> aria-invalid="true"<?php endif; ?>
                <?php if (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_LACK) === true): ?> checked<?php endif; ?>
            >
            <span class="text-sm text-ink">
                <?= e($label) ?>
                <?php if ($required): ?><span class="text-rose-500" aria-hidden="true">*</span><?php endif; ?>
                <?php if ($hint !== ''): ?><span class="mt-0.5 block text-xs text-ink-400"><?= e($hint) ?></span><?php endif; ?>
            </span>
        </label>
    <?php else: ?>
        <input
            type="<?= e($type) ?>"
            id="<?= e($id) ?>"
            name="<?= e($name) ?>"
            value="<?= e((string) $value) ?>"
            class="<?= e($control) ?>"
            placeholder="<?= e($placeholder) ?>"
            <?php if ($required): ?> required<?php endif; ?>
            <?php if ($autocomplete !== ''): ?> autocomplete="<?= e($autocomplete) ?>"<?php endif; ?>
            <?php if ($inputmode !== ''): ?> inputmode="<?= e($inputmode) ?>"<?php endif; ?>
            <?php if ($invalid): ?> aria-invalid="true"<?php endif; ?>
        >
    <?php endif; ?>

    <?php if ($type !== 'checkbox' && $hint !== ''): ?>
        <p class="text-xs text-ink-400"><?= e($hint) ?></p>
    <?php endif; ?>

    <?php if ($invalid): ?>
        <ul class="space-y-0.5">
            <?php foreach ($messages as $message): ?>
                <li class="text-xs font-semibold text-rose-600"><?= e((string) $message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
