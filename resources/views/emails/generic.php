<?php

/**
 * Fallback email body used whenever a caller asks for a template that does not
 * exist, so a missed view can never send an empty message.
 *
 * @var string|null $heading
 * @var list<string>|null $lines
 * @var array{text: string, url: string}|null $button
 */

$heading = (string) ($heading ?? 'Notification');
$rows = (array) ($lines ?? []);
$action = is_array($button ?? null) ? $button : null;
?>
<h1 style="margin:0 0 14px;font-size:21px;line-height:1.3;color:#08201d;">
    <?= e($heading) ?>
</h1>

<?php foreach ($rows as $row): ?>
    <p style="margin:0 0 12px;"><?= e((string) $row) ?></p>
<?php endforeach; ?>

<?php if ($action !== null && (string) ($action['url'] ?? '') !== ''): ?>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0 6px;">
        <tr>
            <td style="border-radius:10px;background:#0b5d50;">
                <a href="<?= e((string) $action['url']) ?>"
                   style="display:inline-block;padding:12px 22px;color:#ffffff;font-weight:600;
                          text-decoration:none;border-radius:10px;">
                    <?= e((string) ($action['text'] ?? t('common.open_button'))) ?>
                </a>
            </td>
        </tr>
    </table>
<?php endif; ?>

<p style="margin:16px 0 0;font-size:12px;color:#5f7a74;">
    <?= e(t('common.email_if_unexpected')) ?>
</p>
