<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: layout.php
 *
 * Last Modified: Wed, 11 Feb 2026 - 20:38:50 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

// Resolve assets path - allows customization when integrated into other apps
$assetsPath = rtrim($config['fm']['assets_path'] ?? '', '/');
if ($assetsPath === '') {
    $assetsPath = 'assets'; // Default: relative to entry point
}
?>

<!DOCTYPE html>
<html lang="<?= $config['fm']['language'] ?? 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? ($config['fm']['title'] ?? 'File Manager') ?></title>
    <link rel="stylesheet" href="<?= $assetsPath ?>/bulma/css/bulma.min.css">
    <link rel="stylesheet" href="<?= $assetsPath ?>/fonts/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $assetsPath ?>/libs/dropzone/5.9.3/dist/min/dropzone.min.css">
    <link rel="stylesheet" href="<?= $assetsPath ?>/libs/bulma-responsive-tables/1.2.5/css/main.min.css">
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/custom.css">
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/context-menu.css">
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/toast.css">
</head>

<body>
<nav class="navbar is-dark" role="navigation" aria-label="main navigation">
    <div class="navbar-brand">
        <a class="navbar-item" href="filemanager.php">
            <i class="fas fa-folder-open mr-2"></i>
            <strong><?= htmlspecialchars($config['fm']['title'] ?? 'File Manager') ?></strong>
        </a>

        <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarMenu">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>

    <div id="navbarMenu" class="navbar-menu">
        <div class="navbar-end">
            <?php if (isset($username)): ?>
                <div class="navbar-item">
                        <span class="tag">
                            <i class="fas fa-user mr-1"></i>
                            <?= htmlspecialchars($username) ?>
                        </span>
                </div>
                <?php if ($config['auth']['require_login'] ?? true): ?>
                    <div class="navbar-item">
                        <a href="?action=logout" class="button is-light is-small">
                            <i class="fas fa-sign-out-alt mr-1"></i>
                            Logout
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php
// Display flash messages
if (isset($messages) && is_object($messages)):
    foreach ($messages->getAll() as $msg):
        ?>
        <div class="notification is-<?= $msg['type'] ?> is-light">
            <button class="delete"></button>
            <?= htmlspecialchars($msg['message']) ?>
        </div>
    <?php
    endforeach;
endif;
?>

<section class="section">
    <div class="container<?= isset($isFluid) && $isFluid ? '-fluid' : '' ?>">
        <?php
        // Main content area - included from child templates
        if (isset($content)) {
            echo $content;
        }
        ?>
    </div>
</section>

<footer class="footer">
    <div class="content has-text-centered">
        <p>
            <strong>PHP File Manager</strong> - PHP <?= PHP_VERSION ?> |
            Built with <a href="https://bulma.io" target="_blank">Bulma</a> |
            <a href="https://github.com/LifeIsHex/php-file-manager" target="_blank"><i class="fab fa-github"></i> GitHub</a>
        </p
    </div>
</footer>

<?php
// Flash message data for toast notifications
if (isset($flashMessage) && is_array($flashMessage)):
    ?>
    <div id="flash-message-data" data-type="<?= htmlspecialchars($flashMessage['type']) ?>"
         data-text="<?= htmlspecialchars($flashMessage['text']) ?>" style="display: none;"></div>
<?php endif; ?>

<script src="<?= $assetsPath ?>/libs/sortable/1.15.6/Sortable.min.js"></script>
<script src="<?= $assetsPath ?>/libs/dropzone/5.9.3/dist/min/dropzone.min.js"></script>
<script src="<?= $assetsPath ?>/js/toast.js"></script>
<script src="<?= $assetsPath ?>/js/app.js"></script>
</body>

</html>