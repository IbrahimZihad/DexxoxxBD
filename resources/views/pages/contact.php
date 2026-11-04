<?php

/**
 * Contact page: direct details plus a ticket form. The form opens a real
 * support_tickets row; nothing is faked as "sent".
 */

use App\Core\Auth;

$user = Auth::instance()->user();

$view->extend('layouts.app');
$view->setSection('title', t('page.contact.title') . ' | ' . (string) setting('brand.name', config('app.name')));
$view->setSection('meta_description', t('page.contact.subtitle'));
$view->setSection('canonical', url('contact'));
?>
<div class="container">
    <?= $view->component('breadcrumb', ['items' => [
        ['label' => t('nav.home'), 'url' => url('')],
        ['label' => t('nav.contact')],
    ]]) ?>

    <div class="dx-pagehead">
        <h1 class="dx-pagehead__title"><?= e(t('page.contact.title')) ?></h1>
        <p class="dx-pagehead__sub"><?= e(t('page.contact.subtitle')) ?></p>
    </div>

    <div class="dx-layout dx-layout--contact">
        <section class="dx-cardpanel">
            <h2 class="dx-h4"><?= e(t('page.contact.form_title')) ?></h2>
            <form method="post" action="<?= e(url('contact')) ?>" class="dx-form" data-throttled>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12<?= $user === null ? ' col-sm-6' : '' ?>">
                        <label class="form-label" for="dx-c-name"><?= e(t('page.contact.name')) ?></label>
                        <input class="form-control" id="dx-c-name" name="name" type="text" required
                               maxlength="120" value="<?= e($user['name'] ?? old('name')) ?>">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="dx-c-email"><?= e(t('auth.email')) ?></label>
                        <input class="form-control" id="dx-c-email" name="email" type="email" required
                               maxlength="190" value="<?= e($user['email'] ?? old('email')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="dx-c-subject"><?= e(t('page.contact.subject')) ?></label>
                        <input class="form-control" id="dx-c-subject" name="subject" type="text" required
                               maxlength="180" value="<?= e(old('subject')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="dx-c-body"><?= e(t('page.contact.message')) ?></label>
                        <textarea class="form-control" id="dx-c-body" name="body" rows="6" required
                                  minlength="20" maxlength="4000"><?= e(old('body')) ?></textarea>
                        <p class="dx-hint"><?= e(t('page.contact.reply_time')) ?></p>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dx btn-lg" type="submit"><?= e(t('page.contact.send')) ?></button>
                    </div>
                </div>
            </form>
        </section>

        <aside class="dx-cardpanel dx-cardpanel--ink">
            <h2 class="dx-h4"><?= e(t('page.contact.or_reach')) ?></h2>
            <ul class="dx-datalist">
                <li>
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                    <span>
                        <strong><?= e(t('auth.email')) ?></strong>
                        <a class="dx-link" href="mailto:<?= e((string) setting('contact.email', '')) ?>">
                            <?= e((string) setting('contact.email', '')) ?>
                        </a>
                    </span>
                </li>
                <li>
                    <i class="bi bi-telephone" aria-hidden="true"></i>
                    <span>
                        <strong><?= e(t('common.phone')) ?></strong>
                        <a class="dx-link" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) setting('contact.phone', ''))) ?>">
                            <?= e((string) setting('contact.phone', '')) ?>
                        </a>
                    </span>
                </li>
                <li>
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <span>
                        <strong><?= e(t('common.address')) ?></strong>
                        <span><?= e((string) setting('contact.address', '')) ?></span>
                    </span>
                </li>
                <li>
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    <span>
                        <strong><?= e(t('page.contact.hours')) ?></strong>
                        <span><?= e((string) setting('contact.hours', '')) ?></span>
                    </span>
                </li>
            </ul>
            <p class="dx-muted"><?= e(t('page.help_text', ['email' => (string) setting('contact.email', '')])) ?></p>
        </aside>
    </div>
</div>
