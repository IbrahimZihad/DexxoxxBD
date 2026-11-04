<?php

/**
 * Standalone error document.
 *
 * Deliberately not built on layouts/app: that layout reads banners, menus and
 * settings from MySQL, and this page is exactly what renders when MySQL, a
 * controller or the route itself failed. Only filesystem-backed helpers (t(),
 * url(), config()) are used so this template cannot throw on its own.
 *
 * $status, $title, $message, $errors, $allowed, $incident come from
 * ErrorHandler; $incident is only ever populated when APP_DEBUG is on.
 */

$copy = match ($status) {
    400, 405 => 'error.400',
    401, 403 => 'error.403',
    404 => 'error.404',
    422, 429 => 'error.429',
    503 => 'error.503',
    default => 'error.500',
};

$heading = t($copy . '.title');
$body = t($copy . '.text');
// t() humanises an unknown key, so a miss would show "Title" - keep the
// message the handler already chose in that case.
if ($heading === 'Title') {
    $heading = $title;
}

$links = match ($status) {
    404 => [[url('products'), t('error.404.cta')], [url(''), t('error.404.home')]],
    401, 403 => [[url('login'), t('error.403.cta')], [url(''), t('error.404.home')]],
    500 => [[url('contact'), t('error.500.cta')], [url(''), t('error.500.home')]],
    503 => [[url(''), t('error.503.cta')]],
    default => [[url(''), t('error.400.cta')]],
};

$siteName = (string) config('app.name', 'DexxoxxBD');
$lang = current_locale();
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#08201d">
    <title><?= e($status . ' - ' . $heading) ?> | <?= e($siteName) ?></title>
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="dx-errorbody">
<main class="dx-error">
    <p class="dx-error__code"><?= e((string) $status) ?></p>
    <h1 class="dx-error__heading"><?= e($heading) ?></h1>
    <p class="dx-error__text"><?= e($body) ?></p>

    <?php if ($message !== '' && !str_contains(strtolower($message), strtolower($heading))): ?>
        <p class="dx-error__detail"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="dx-error__list">
            <?php foreach ($errors as $field => $lines): ?>
                <p class="dx-error__line"><strong><?= e(ucfirst(str_replace('_', ' ', (string) $field))) ?>:</strong>
                    <?= e(implode(', ', array_map('strval', (array) $lines))) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($allowed !== []): ?>
        <p class="dx-error__detail"><?= e(t('error.allowed')) ?>: <?= e(implode(', ', $allowed)) ?></p>
    <?php endif; ?>

    <nav class="dx-error__actions" aria-label="<?= e(t('error.actions')) ?>">
        <?php foreach ($links as [$href, $label]): ?>
            <a class="btn <?= $href === url('') ? 'btn-outline-light' : 'btn-warning' ?>"
               href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($incident !== null && $status >= 500): ?>
        <section class="dx-error__debug">
            <p class="dx-error__ref"><?= e(t('error.debug_note')) ?></p>
            <pre class="dx-error__trace"><?= e(
                $incident['class'] . ': ' . $incident['message'] . "\n"
                . $incident['file'] . ':' . $incident['line'] . "\n\n"
                . implode("\n", $incident['trace'])
            ) ?></pre>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
