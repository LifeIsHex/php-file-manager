<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: select_destination.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:39:41 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

/**
 * Destination Browser Template
 * Allows user to navigate folders and select destination for copy/move operations
 */

$operationLabel = ucfirst($operation['operation']);
$sourceFile = $operation['source'];
$sourcePath = $operation['sourcePath'];
$sourceDisplay = $sourcePath ? $sourcePath . '/' . $sourceFile : $sourceFile;

// Get base URL from config (for CI4/Laravel integration)
$baseUrl = $config['fm']['base_url'] ?? 'index.php';
?>

<div class="box">
    <div class="level">
        <div class="level-left">
            <div class="level-item">
                <div>
                    <h1 class="title is-4">
                        <i class="fas fa-<?= $operation['operation'] === 'copy' ? 'copy' : 'arrows-alt' ?> mr-2"></i>
                        <?= $operationLabel ?> - Select Destination
                    </h1>
                    <p class="subtitle is-6 mt-5">
                        <strong><?= $operationLabel ?>:</strong>
                        <span class="tag is-info"><?= htmlspecialchars($sourceFile) ?></span>
                        <br>
                        <br>
                        <strong>From:</strong> <?= htmlspecialchars($sourcePath ?: '/') ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="level-right">
            <div class="level-item">
                <form method="GET" action="<?= htmlspecialchars($baseUrl) ?>" style="display: inline;">
                    <input type="hidden" name="p" value="<?= htmlspecialchars($sourcePath) ?>">
                    <button type="submit" class="button is-light"
                            onclick="<?= htmlspecialchars(json_encode(['session' => ['clearPendingOperation' => true]])) ?>">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs">
    <ul>
        <li><a href="?action=select-destination&p="><i class="fas fa-home mr-1"></i> Home</a></li>
        <?php foreach ($breadcrumbs as $crumb): ?>
            <li <?= $crumb['path'] === $currentPath ? 'class="is-active"' : '' ?>>
                <a href="?action=select-destination&p=<?= urlencode($crumb['path']) ?>">
                    <?= htmlspecialchars($crumb['name']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<!-- Folder List -->
<div class="box" style="min-height: 400px;">
    <h2 class="title is-5">
        <i class="fas fa-folder-open mr-2"></i>
        Select Destination Folder
    </h2>

    <?php if (empty($folders)): ?>
        <div class="notification is-info is-light">
            <i class="fas fa-info-circle mr-2"></i>
            No subfolders in this location. You can select this folder as the destination.
        </div>
    <?php else: ?>
        <div class="table-container" style="max-height: 500px; overflow-y: auto;">
            <table class="table is-fullwidth is-hoverable">
                <thead>
                <tr>
                    <th><i class="fas fa-folder mr-2"></i> Folder Name</th>
                    <th width="150">Modified</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($folders as $folder): ?>
                    <tr style="cursor: pointer;"
                        onclick="window.location='?action=select-destination&p=<?= urlencode($currentPath ? $currentPath . '/' . $folder['name'] : $folder['name']) ?>'">
                        <td>
                            <i class="fas fa-folder has-text-warning mr-2"></i>
                            <strong><?= htmlspecialchars($folder['name']) ?></strong>
                        </td>
                        <td>
                            <span class="is-size-7"><?= date('Y-m-d H:i', $folder['modified']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Select This Folder Button -->
    <div class="mt-5">
        <form method="POST" action="<?= htmlspecialchars($baseUrl) ?>?action=execute-copy-move">
            <input type="hidden" name="destination" value="<?= htmlspecialchars($currentPath) ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf->generateToken() ?>">

            <div class="field is-grouped">
                <div class="control">
                    <button type="submit" class="button is-success is-medium">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= $operationLabel ?> to This Folder:
                        <strong class="ml-2"><?= htmlspecialchars($currentPath ?: '/') ?></strong>
                    </button>
                </div>
                <div class="control">
                    <a href="<?= htmlspecialchars($baseUrl) ?>?p=<?= urlencode($sourcePath) ?>" class="button is-light is-medium">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>