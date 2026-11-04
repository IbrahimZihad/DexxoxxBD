<?php

/**
 * Flash messages and validation errors carried in the session.
 *
 * $messages is a list of ['type' => ..., 'message' => ...]; $errors is a map of
 * field => list of messages. Both are shared by App::shareViewGlobals().
 */

$types = ['success' => 'bi-check-circle', 'error' => 'bi-exclamation-octagon', 'warning' => 'bi-exclamation-triangle',
    'info' => 'bi-info-circle'];
?>
<?php if ($messages !== [] || $errors !== []): ?>
    <div class="container">
        <?php foreach ($messages as $message): ?>
            <?php $type = (string) ($message['type'] ?? 'info'); ?>
            <div class="dx-flash dx-flash--<?= e($type) ?>" role="status">
                <i class="bi <?= e($types[$type] ?? 'bi-info-circle') ?>" aria-hidden="true"></i>
                <span><?= e((string) $message['message']) ?></span>
            </div>
        <?php endforeach; ?>

        <?php if ($errors !== []): ?>
            <div class="dx-flash dx-flash--error" role="alert">
                <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                <div>
                    <?php foreach ($errors as $field => $lines): ?>
                        <?php foreach ((array) $lines as $line): ?>
                            <p class="dx-flash__line"><?= e((string) $line) ?></p>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
