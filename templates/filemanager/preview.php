<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: preview.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:29:27 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

use FileManager\Security\Validator;

$title = 'View File - ' . $filename;
$username = $this->auth->getUsername();

// Get base URL from config (for CI4/Laravel integration)
$baseUrl = $config['fm']['base_url'] ?? 'index.php';

ob_start();
?>

    <div class="columns">
        <div class="column is-three-quarters">
            <nav class="breadcrumb" aria-label="breadcrumbs">
                <ul>
                    <li><a href="<?= htmlspecialchars($baseUrl) ?>">Home</a></li>
                    <?php if ($currentPath): ?>
                        <li><a href="<?= htmlspecialchars($baseUrl) ?>?p=<?= urlencode($currentPath) ?>"><?= Validator::escape($currentPath) ?></a></li>
                    <?php endif; ?>
                    <li class="is-active"><a href="#" aria-current="page"><?= Validator::escape($filename) ?></a></li>
                </ul>
            </nav>

            <div class="box">
                <h1 class="title is-4">
                    <i class="fas fa-file mr-2"></i>
                    <?= Validator::escape($filename) ?>
                </h1>

                <?php if ($isImage): ?>
                    <!-- Image Preview -->
                    <figure class="image">
                        <img src="data:<?= $mimeType ?>;base64,<?= base64_encode($fileContent) ?>"
                             alt="<?= Validator::escape($filename) ?>">
                    </figure>
                <?php elseif ($isText): ?>
                    <!-- Text Editor -->
                    <form method="POST" action="?action=save" id="editForm">
                        <input type="hidden" name="p" value="<?= Validator::escape($currentPath) ?>">
                        <input type="hidden" name="file" value="<?= Validator::escape($filename) ?>">

                        <div class="field">
                            <div class="control">
                            <textarea class="textarea" name="content" id="editor" rows="25"
                                      style="font-family: 'Courier New', monospace;"><?= Validator::escape($fileContent) ?></textarea>
                            </div>
                        </div>

                        <div class="field is-grouped">
                            <div class="control">
                                <button type="submit" class="button is-primary">
                                    <i class="fas fa-save mr-2"></i>
                                    Save Changes
                                </button>
                            </div>
                            <div class="control">
                                <a href="<?= htmlspecialchars($baseUrl) ?>?p=<?= urlencode($currentPath) ?>" class="button is-light">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Binary/Unsupported File -->
                    <div class="notification is-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        This file type cannot be previewed or edited.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="column">
            <div class="box">
                <h2 class="title is-5">File Information</h2>

                <table class="table is-fullwidth">
                    <tbody>
                    <tr>
                        <th>File Name</th>
                        <td><?= Validator::escape($filename) ?></td>
                    </tr>
                    <tr>
                        <th>Full Path</th>
                        <td class="is-family-monospace is-size-7"><?= Validator::escape($fullPath) ?></td>
                    </tr>
                    <tr>
                        <th>File Size</th>
                        <td><?= $fileInfo['size_formatted'] ?></td>
                    </tr>
                    <tr>
                        <th>MIME Type</th>
                        <td><?= Validator::escape($mimeType) ?></td>
                    </tr>
                    <?php if ($isImage && isset($imageInfo)): ?>
                        <tr>
                            <th>Dimensions</th>
                            <td><?= $imageInfo[0] ?> × <?= $imageInfo[1] ?> px</td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Modified</th>
                        <td><?= date('Y-m-d H:i:s', $fileInfo['modified']) ?></td>
                    </tr>
                    <tr>
                        <th>Permissions</th>
                        <td class="is-family-monospace"><?= $fileInfo['permissions'] ?></td>
                    </tr>
                    </tbody>
                </table>

                <div class="buttons">
                    <a href="?action=download&p=<?= urlencode($currentPath) ?>&file=<?= urlencode($filename) ?>"
                       class="button is-info is-fullwidth">
                        <i class="fas fa-download mr-2"></i>
                        Download
                    </a>
                    <a href="<?= htmlspecialchars($baseUrl) ?>?p=<?= urlencode($currentPath) ?>" class="button is-light is-fullwidth">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Files
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
?>