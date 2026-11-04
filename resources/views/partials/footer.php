<?php

/**
 * Site footer: brand block, the CMS footer menu, published footer pages,
 * newsletter signup, accepted payment methods and the legal line.
 */

use App\Repositories\ContentRepository;

$repo = new ContentRepository();
$menu = $repo->menu('footer');
$pages = $repo->footerPages(8);
$brandName = (string) setting('brand.name', config('app.name', 'DexxoxxBD'));
$social = (array) setting('social.links', []);
$icons = [
    'facebook' => 'bi-facebook',
    'youtube' => 'bi-youtube',
    'linkedin' => 'bi-linkedin',
    'instagram' => 'bi-instagram',
    'twitter' => 'bi-twitter-x',
    'x' => 'bi-twitter-x',
    'whatsapp' => 'bi-whatsapp',
    'telegram' => 'bi-telegram',
    'github' => 'bi-github',
];
$payments = ['bKash', 'Nagad', 'Rocket', 'SSLCommerz card', 'Bank transfer'];
?>
<footer class="dx-footer">
    <div class="container">
        <div class="dx-footer__grid">
            <section class="dx-footer__brand">
                <a class="dx-logo dx-logo--footer" href="<?= e(url('/')) ?>">
                    <span class="dx-logo__mark" aria-hidden="true">D<span class="dx-logo__x">X</span></span>
                    <span class="dx-logo__word">
                        <?= e(mb_substr($brandName, 0, 7)) ?><em><?= e(mb_substr($brandName, 7)) ?></em>
                    </span>
                </a>
                <p><?= e(t('footer.blurb')) ?></p>
                <ul class="dx-footer__contact">
                    <li>
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                        <?= e((string) setting('contact.address', config('app.brand.address'))) ?>
                    </li>
                    <li>
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <a href="mailto:<?= e((string) setting('contact.email', '')) ?>">
                            <?= e((string) setting('contact.email', '')) ?>
                        </a>
                    </li>
                    <li>
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        <?= e((string) setting('contact.hours', config('app.brand.hours'))) ?>
                    </li>
                </ul>
                <?php if ($social !== []): ?>
                    <ul class="dx-footer__social" aria-label="<?= e(t('nav.follow_us')) ?>">
                        <?php foreach ($social as $network => $href): ?>
                            <?php
                            $host = strtolower((string) parse_url((string) $href, PHP_URL_HOST));
                            $key = strtr($host, ['www.' => '']);
                            $icon = 'bi-link-45deg';
                            foreach ($icons as $needle => $class) {
                                if (str_contains($key, $needle)) {
                                    $icon = $class;
                                    break;
                                }
                            }
                            ?>
                            <li>
                                <a href="<?= e((string) $href) ?>" rel="me noopener" target="_blank"
                                   title="<?= e(ucfirst((string) $network)) ?>">
                                    <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                                    <span class="visually-hidden"><?= e((string) $network) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <nav class="dx-footer__col" aria-label="<?= e(t('footer.shop')) ?>">
                <h2 class="dx-footer__title"><?= e(t('footer.shop')) ?></h2>
                <ul class="dx-footer__list">
                    <?php foreach ($menu as $item): ?>
                        <li>
                            <a href="<?= e((string) $item['href']) ?>"><?= e((string) $item['label']) ?></a>
                            <?php if ($item['children'] !== []): ?>
                                <ul class="dx-footer__sublist">
                                    <?php foreach ($item['children'] as $child): ?>
                                        <li>
                                            <a href="<?= e((string) $child['href']) ?>">
                                                <?= e((string) $child['label']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <nav class="dx-footer__col" aria-label="<?= e(t('footer.help')) ?>">
                <h2 class="dx-footer__title"><?= e(t('footer.help')) ?></h2>
                <ul class="dx-footer__list">
                    <?php foreach ($pages as $page): ?>
                        <li>
                            <a href="<?= e(url('page/' . $page['slug'])) ?>"><?= e((string) $page['title']) ?></a>
                        </li>
                    <?php endforeach; ?>
                    <li><a href="<?= e(url('faqs')) ?>"><?= e(t('nav.faqs')) ?></a></li>
                    <li><a href="<?= e(url('contact')) ?>"><?= e(t('nav.contact')) ?></a></li>
                    <li><a href="<?= e(url('how-it-works')) ?>"><?= e(t('nav.how_it_works')) ?></a></li>
                </ul>
            </nav>

            <section class="dx-footer__col dx-newsletter">
                <h2 class="dx-footer__title"><?= e(t('home.newsletter.title')) ?></h2>
                <p><?= e(t('home.newsletter.subtitle')) ?></p>
                <form method="post" action="<?= e(url('newsletter')) ?>" class="dx-newsletter__form">
                    <?= csrf_field() ?>
                    <label class="visually-hidden" for="dx-news-email">
                        <?= e(t('auth.email')) ?>
                    </label>
                    <input class="form-control" id="dx-news-email" name="email" type="email" required
                           maxlength="190" placeholder="<?= e(t('home.newsletter.placeholder')) ?>">
                    <button class="btn btn-dx" type="submit"><?= e(t('home.newsletter.cta')) ?></button>
                    <small><?= e(t('home.newsletter.consent')) ?></small>
                </form>
            </section>
        </div>

        <div class="dx-footer__payments">
            <span class="dx-footer__paylabel"><?= e(t('footer.payments')) ?></span>
            <?php foreach ($payments as $method): ?>
                <span class="dx-pay-chip"><?= e($method) ?></span>
            <?php endforeach; ?>
        </div>

        <div class="dx-footer__legal">
            <p>
                &copy; <?= e(date('Y')) ?> <?= e($brandName) ?>. <?= e(t('footer.rights')) ?>
                <span class="d-none d-md-inline"><?= e(t('footer.currency_note')) ?></span>
                <span class="d-none d-lg-inline"><?= e(t('footer.time_zone')) ?></span>
            </p>
            <p class="dx-footer__note"><?= e(t('footer.brand_note')) ?></p>
        </div>
    </div>
</footer>
