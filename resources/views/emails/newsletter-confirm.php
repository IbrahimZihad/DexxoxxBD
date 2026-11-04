<?php

/**
 * Double opt-in confirmation email.
 *
 * The link carries the only copy of the token we ever see in the clear: the
 * database keeps its SHA-256 hash, so a leaked table cannot confirm addresses.
 *
 * @var string|null $heading
 * @var list<string>|null $lines
 * @var array{text: string, url: string}|null $button
 */

$rows = (array) ($lines ?? []);
$action = is_array($button ?? null) ? $button : null;
?>
<h1 style="margin:0 0 14px;font-size:21px;line-height:1.3;color:#08201d;">
    <?= e((string) ($heading ?? t('page.newsletter.confirm_heading'))) ?>
</h1>

<?php foreach ($rows as $row): ?>
    <p style="margin:0 0 12px;"><?= e((string) $row) ?></p>
<?php endforeach; ?>

<?php if ($action !== null && (string) ($action['url'] ?? '') !== ''): ?>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0 6px;">
        <tr>
            <td style="border-radius:10px;background:#0b5d50;">
                <a href="<?= e((string) $action['url']) ?>"
                   style="display:inline-block;padding:13px 24px;color:#ffffff;font-weight:600;
                          text-decoration:none;border-radius:10px;">
                    <?= e((string) ($action['text'] ?? t('page.newsletter.confirm_cta'))) ?>
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:12px 0 0;font-size:12px;color:#5f7a74;word-break:break-all;">
        <?= e(t('common.email_paste_link')) ?>
        <a href="<?= e((string) $action['url']) ?>" style="color:#0b5d50;">
            <?= e((string) $action['url']) ?>
        </a>
    </p>
<?php endif; ?>

<p style="margin:18px 0 0;padding:12px 14px;background:#f4f7f5;border-radius:10px;font-size:12px;color:#5f7a74;">
    <?= e(t('common.email_if_unexpected')) ?>
</p>
