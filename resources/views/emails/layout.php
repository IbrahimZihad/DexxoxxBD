<?php

/**
 * Transactional email shell.
 *
 * Table layout and inline styles because mail clients ignore external CSS; the
 * wordmark is text, so a message never depends on reaching this server to
 * render. $body is HTML produced by another email template and is printed as-is.
 *
 * @var string $title
 * @var string $body
 * @var string $preheader
 */

$siteName = (string) setting('brand.name', config('app.name', 'DexxoxxBD'));
$supportEmail = (string) setting('contact.email', config('app.brand.support_email', ''));
$supportPhone = (string) setting('contact.phone', config('app.brand.support_phone', ''));
$address = (string) setting('contact.address', config('app.brand.address', ''));
?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <title><?= e($title ?? $siteName) ?></title>
</head>
<body style="margin:0;padding:0;background:#eef3f0;">
<?php if (trim((string) ($preheader ?? '')) !== ''): ?>
    <span style="display:none;max-height:0;overflow:hidden;opacity:0;">
        <?= e((string) $preheader) ?>
    </span>
<?php endif; ?>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3f0;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                   style="width:600px;max-width:100%;background:#ffffff;border-radius:14px;overflow:hidden;
                          border:1px solid #d7e2dd;font-family:'Segoe UI',system-ui,Arial,sans-serif;
                          color:#08201d;font-size:15px;line-height:1.55;">
                <tr>
                    <td style="background:#08201d;padding:18px 24px;">
                        <a href="<?= e(url('')) ?>"
                           style="color:#ffffff;text-decoration:none;font-size:19px;font-weight:700;letter-spacing:-.02em;">
                            <?= e($siteName) ?>
                        </a>
                        <span style="display:block;color:#6fd3c2;font-size:12px;margin-top:2px;">
                            <?= e(t('common.email_tagline')) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 24px 8px;">
                        <?= $body ?? '' ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 24px 26px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="border-top:1px solid #d7e2dd;">
                            <tr>
                                <td style="padding-top:16px;font-size:12px;color:#5f7a74;">
                                    <?php if ($supportEmail !== ''): ?>
                                        <a href="mailto:<?= e($supportEmail) ?>"
                                           style="color:#0b5d50;text-decoration:underline;">
                                            <?= e($supportEmail) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($supportPhone !== ''): ?>
                                        &nbsp;·&nbsp;<?= e($supportPhone) ?>
                                    <?php endif; ?>
                                    <?php if ($address !== ''): ?>
                                        <br><?= e($address) ?>
                                    <?php endif; ?>
                                    <br>
                                    <a href="<?= e(url('privacy')) ?>" style="color:#5f7a74;">
                                        <?= e(t('nav.privacy')) ?>
                                    </a>
                                    &nbsp;·&nbsp;
                                    <a href="<?= e(url('terms')) ?>" style="color:#5f7a74;">
                                        <?= e(t('nav.terms')) ?>
                                    </a>
                                    &nbsp;·&nbsp;
                                    <a href="<?= e(url('')) ?>" style="color:#5f7a74;">
                                        <?= e(t('common.email_in_browser')) ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
