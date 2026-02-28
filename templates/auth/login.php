<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: login.php
 *
 * Last Modified: Sat, 28 Feb 2026 - 12:29:43 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

use FileManager\Security\Validator;

// Resolve assets path - allows customization when integrated into other apps
$assetsPath = rtrim($config['fm']['assets_path'] ?? '', '/');
if ($assetsPath === '') {
    $assetsPath = 'assets'; // Default: relative to entry point
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - File Manager</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetsPath) ?>/bulma/css/bulma.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetsPath) ?>/fonts/font-awesome/6.5.1/css/all.min.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>

<body>
<div class="login-container">
    <div class="box" style="width: 400px; max-width: 90%;">
        <div class="has-text-centered mb-5">
            <i class="fas fa-folder-open fa-3x has-text-primary mb-3"></i>
            <h1 class="title">File Manager</h1>
            <p class="subtitle is-6">Please sign in to continue</p>
        </div>

        <?php
        // Display messages
        if (isset($messages) && is_object($messages)):
            foreach ($messages->getAll() as $msg):
                ?>
                <div class="notification is-<?= htmlspecialchars($msg['type']) ?> is-light">
                    <button class="delete"></button>
                    <?= Validator::escape($msg['message']) ?>
                </div>
            <?php
            endforeach;
        endif;
        ?>

        <form method="POST" action="">
            <?= $csrf->getTokenField() ?>

            <div class="field">
                <label class="label">Username</label>
                <div class="control has-icons-left">
                    <input class="input" type="text" name="username" placeholder="Username" required autofocus>
                    <span class="icon is-small is-left">
                            <i class="fas fa-user"></i>
                        </span>
                </div>
            </div>

            <div class="field">
                <label class="label">Password</label>
                <div class="control has-icons-left">
                    <input class="input" type="password" name="password" placeholder="Password" required>
                    <span class="icon is-small is-left">
                            <i class="fas fa-lock"></i>
                        </span>
                </div>
            </div>

            <?php if (($config['auth']['remember_me'] ?? false)): ?>
                <div class="field">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="remember_me">
                            Remember me for 30 minutes
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="field">
                <div class="control">
                    <button type="submit" class="button is-primary is-fullwidth">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </button>
                </div>
            </div>
        </form>


    </div>
</div>

<script>
    // Auto-hide notifications
    document.querySelectorAll('.notification .delete').forEach(button => {
        button.addEventListener('click', () => {
            button.parentElement.remove();
        });
    });
</script>
</body>

</html>