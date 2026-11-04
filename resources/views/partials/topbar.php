<?php

/**
 * Slim strip above the header: the live announcement banner, contact details and
 * the language switch.
 */

use App\Repositories\ContentRepository;

$announcements = (new ContentRepository())->banners('announcement', current_locale(), 1);
$announcement = $announcements[0] ?? null;
$social = setting('social.links', []);
?>
<div class="dx-topbar">
    <div class="container dx-topbar__inner">
        <p class="dx-topbar__news">
            <i class="bi bi-megaphone" aria-hidden="true"></i>
            <?php if ($announcement !== null): ?>
                <a href="<?= e(link_url((string) ($announcement['link'] ?? ''))) ?>">
                    <?= e((string) $announcement['title']) ?>
                </a>
            <?php else: ?>
                <?= e((string) setting('homepage.announcement', t('home.announcement.default'))) ?>
            <?php endif; ?>
        </p>

        <div class="dx-topbar__aside">
            <a class="dx-topbar__link" href="tel:<?= e(preg_replace('/\s+/', '', (string) setting('contact.phone', ''))) ?>">
                <i class="bi bi-telephone" aria-hidden="true"></i>
                <span class="d-none d-md-inline"><?= e((string) setting('contact.phone', config('app.brand.support_phone'))) ?></span>
            </a>
            <a class="dx-topbar__link" href="mailto:<?= e((string) setting('contact.email', '')) ?>">
                <i class="bi bi-envelope" aria-hidden="true"></i>
                <span class="d-none d-md-inline"><?= e((string) setting('contact.email', config('app.brand.support_email'))) ?></span>
            </a>
            <span class="dx-topbar__switch" role="group" aria-label="<?= e(t('common.language')) ?>">
                <?php foreach (['en' => t('common.english'), 'bn' => t('common.bengali')] as $code => $label): ?>
                    <a class="dx-topbar__lang<?= $code === current_locale() ? ' is-active' : '' ?>"
                       href="<?= e(url('locale/' . $code)) ?>"
                       lang="<?= e($code) ?>"
                       <?= $code === current_locale() ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </span>
        </div>
    </div>
</div>
