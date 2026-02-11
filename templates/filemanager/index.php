<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: index.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 20:00:23 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

use FileManager\Security\Validator;

$title = 'File Manager';
$username = $this->auth->getUsername();
$isFluid = true;

ob_start();
?>

    <!-- Breadcrumb Navigation -->
    <nav class="breadcrumb" aria-label="breadcrumbs">
        <ul>
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php if ($index === count($breadcrumbs) - 1): ?>
                    <li class="is-active"><a href="#" aria-current="page"><?= Validator::escape($crumb['name']) ?></a></li>
                <?php else: ?>
                    <li><a href="?p=<?= urlencode($crumb['path']) ?>"><?= Validator::escape($crumb['name']) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Action Buttons -->
    <div class="level mb-4">
        <div class="level-left">
            <div class="level-item">
                <div class="buttons">
                    <?php if ($permissions->can('upload')): ?>
                        <button class="button is-primary" onclick="showUploadModal()">
                            <i class="fas fa-upload mr-2"></i>
                            Upload Files
                        </button>
                    <?php endif; ?>
                    <?php if ($permissions->can('new_folder')): ?>
                        <button class="button is-link" onclick="showNewFolderModal()">
                            <i class="fas fa-folder-plus mr-2"></i>
                            New Folder
                        </button>
                    <?php endif; ?>
                    <?php if ($permissions->can('copy') || $permissions->can('move')): ?>
                        <button class="button is-info" id="pasteButton" onclick="performToolbarPaste()" style="display: none;">
                            <i class="fas fa-paste mr-2"></i> Paste
                        </button>
                    <?php endif; ?>

                    <!-- Selection Action Buttons (appear when items are selected) -->
                    <span id="selectionActions" style="display: none;">
                        <span class="tag is-large is-light mr-2" id="selectionCounter">0 selected</span>
                        <?php if ($permissions->can('copy')): ?>
                            <button class="button is-success" onclick="copyToClipboard(null, 'copy')" title="Copy selected">
                            <i class="fas fa-copy mr-2"></i> Copy
                        </button>
                        <?php endif; ?>
                        <?php if ($permissions->can('move')): ?>
                            <button class="button is-warning" onclick="copyToClipboard(null, 'cut')" title="Cut selected">
                            <i class="fas fa-cut mr-2"></i> Cut
                        </button>
                        <?php endif; ?>
                        <?php if ($permissions->can('download')): ?>
                            <button class="button is-info" onclick="downloadSelected()" title="Download selected">
                            <i class="fas fa-download mr-2"></i> Download
                        </button>
                        <?php endif; ?>
                        <?php if ($permissions->can('delete')): ?>
                            <button class="button is-danger" onclick="deleteSelectedItems()" title="Delete selected">
                            <i class="fas fa-trash mr-2"></i> Delete
                        </button>
                        <?php endif; ?>
                    </span>

                    <?php if ($permissions->can('zip')): ?>
                        <button class="button is-warning" onclick="showCompressModal()">
                            <i class="fas fa-file-archive mr-2"></i>
                            Compress Selected
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="level-right">
            <div class="level-item">
                <div class="field has-addons">
                    <div class="control has-icons-left">
                        <input class="input" type="text" id="searchInput" placeholder="Search files...">
                        <span class="icon is-left">
                        <i class="fas fa-search"></i>
                    </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- File/Directory Listing -->
    <div class="box">
        <script>
            function submitCompressForm() {
                const form = document.getElementById('compressForm');
                const checkboxes = document.querySelectorAll('.item-checkbox:checked');
                const items = Array.from(checkboxes).map(cb => cb.value);

                // Create hidden inputs for each item
                items.forEach(item => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'items[]';
                    input.value = item;
                    form.appendChild(input);
                });

                form.submit();
            }
        </script>

        <div class="table-container">
            <table class="table is-fullwidth is-hoverable is-striped" id="fileTable">
                <thead>
                <tr>
                    <th width="30">
                        <label class="checkbox">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        </label>
                    </th>
                    <th data-sortable style="cursor: pointer;">Name</th>
                    <th data-sortable style="width: 120px; cursor: pointer;">Size</th>
                    <th class="is-hidden-mobile" data-sortable style="width: 120px; cursor: pointer;">Owner</th>
                    <th data-sortable style="width: 180px; cursor: pointer;">Modified</th>
                    <th class="is-hidden-mobile" data-sortable style="width: 100px; cursor: pointer;">Permissions</th>
                    <th style="width: 220px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                <!-- Parent Directory Link -->
                <?php if ($currentPath !== ''): ?>
                    <tr>
                        <td></td>
                        <td colspan="6">
                            <a href="?p=<?= urlencode(dirname($currentPath)) ?>" class="has-text-link">
                                <i class="fas fa-level-up-alt mr-2"></i>
                                <strong>.. (Parent Directory)</strong>
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>

                <!-- Directories -->
                <?php foreach ($contents['directories'] as $dir): ?>
                    <tr class="file-row directory-row" data-name="<?= Validator::escape($dir['name']) ?>">
                        <td>
                            <label class="checkbox">
                                <input type="checkbox" class="item-checkbox" value="<?= Validator::escape($dir['name']) ?>">
                            </label>
                        </td>
                        <td>
                            <a href="?p=<?= urlencode($currentPath ? $currentPath . '/' . $dir['name'] : $dir['name']) ?>"
                               class="has-text-link">
                                <i class="fas <?= $dir['icon'] ?> mr-2 has-text-info"></i>
                                <strong><?= Validator::escape($dir['name']) ?></strong>
                            </a>
                        </td>
                        <td>-</td>
                        <td class="has-text-grey is-hidden-mobile"><?= Validator::escape($dir['owner']) ?></td>
                        <td><?= date($config['fm']['datetime_format'] ?? 'Y-m-d H:i', $dir['modified']) ?></td>
                        <td class="has-text-grey is-family-monospace is-size-7 is-hidden-mobile"><?= $dir['permissions'] ?></td>
                        <td>
                            <div class="buttons are-small">
                                <?php if ($permissions->can('rename')): ?>
                                    <button class="button is-warning"
                                            onclick="renameItem('<?= Validator::escape($dir['name']) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($permissions->can('copy')): ?>
                                    <a href="?action=copy&file=<?= urlencode($dir['name']) ?>&p=<?= urlencode($currentPath) ?>"
                                       class="button is-info" title="Copy">
                                        <i class="fas fa-copy"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($permissions->can('move')): ?>
                                    <a href="?action=move&file=<?= urlencode($dir['name']) ?>&p=<?= urlencode($currentPath) ?>"
                                       class="button is-primary" title="Move">
                                        <i class="fas fa-arrows-alt"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($permissions->can('permissions')): ?>
                                    <button class="button is-info"
                                            onclick="changePermissions('<?= Validator::escape($dir['name']) ?>', '<?= $dir['permissions'] ?>')">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($permissions->can('download')): ?>
                                    <a href="?action=download&p=<?= urlencode($currentPath) ?>&file=<?= urlencode($dir['name']) ?>"
                                       class="button is-info" title="Download as ZIP">
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($permissions->can('delete')): ?>
                                    <button class="button is-danger" onclick="deleteItem('<?= Validator::escape($dir['name']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- Files -->
                <?php foreach ($contents['files'] as $file): ?>
                    <tr class="file-row" data-name="<?= Validator::escape($file['name']) ?>">
                        <td>
                            <label class="checkbox">
                                <input type="checkbox" class="item-checkbox" value="<?= Validator::escape($file['name']) ?>">
                            </label>
                        </td>
                        <td>
                            <i class="fas <?= $file['icon'] ?> mr-2"></i>
                            <?= Validator::escape($file['name']) ?>
                        </td>
                        <td><?= $file['size_formatted'] ?></td>
                        <td class="has-text-grey is-hidden-mobile"><?= Validator::escape($file['owner']) ?></td>
                        <td><?= date($config['fm']['datetime_format'] ?? 'Y-m-d H:i', $file['modified']) ?></td>
                        <td class="has-text-grey is-family-monospace is-size-7 is-hidden-mobile"><?= $file['permissions'] ?></td>
                        <td>
                            <div class="buttons are-small">
                                <?php if ($permissions->can('extract') && in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['zip'])): ?>
                                    <button class="button is-link"
                                            onclick="showExtractModal('<?= Validator::escape($file['name']) ?>')" title="Extract">
                                        <i class="fas fa-file-zipper"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($permissions->can('view_pdf') && str_ends_with(strtolower($file['name']), '.pdf')): ?>
                                    <a href="?action=view-pdf&p=<?= urlencode($currentPath) ?>&file=<?= urlencode($file['name']) ?>"
                                       class="button is-danger" target="_blank" title="View PDF in Browser">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($permissions->can('view')): ?>
                                    <a href="?action=view&p=<?= urlencode($currentPath) ?>&file=<?= urlencode($file['name']) ?>"
                                       class="button is-success">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($permissions->can('download')): ?>
                                    <a href="?action=download&p=<?= urlencode($currentPath) ?>&file=<?= urlencode($file['name']) ?>"
                                       class="button is-info">
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($permissions->can('rename')): ?>
                                    <button class="button is-warning"
                                            onclick="renameItem('<?= Validator::escape($file['name']) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($permissions->can('copy')): ?>
                                    <a href="?action=copy&file=<?= urlencode($file['name']) ?>&p=<?= urlencode($currentPath) ?>"
                                       class="button is-info" title="Copy">
                                        <i class="fas fa-copy"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($permissions->can('move')): ?>
                                    <a href="?action=move&file=<?= urlencode($file['name']) ?>&p=<?= urlencode($currentPath) ?>"
                                       class="button is-primary" title="Move">
                                        <i class="fas fa-arrows-alt"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($permissions->can('permissions')): ?>
                                    <button class="button is-link"
                                            onclick="changePermissions('<?= Validator::escape($file['name']) ?>', '<?= $file['permissions'] ?>')">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($permissions->can('delete')): ?>
                                    <button class="button is-danger"
                                            onclick="deleteItem('<?= Validator::escape($file['name']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($contents['directories']) && empty($contents['files'])): ?>
                    <tr>
                        <td colspan="7" class="has-text-centered has-text-grey">
                            <i class="fas fa-folder-open fa-2x mb-3"></i>
                            <p>This folder is empty</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- File Statistics -->
    <div class="box has-background-light mt-4">
        <div class="level is-mobile">
            <div class="level-item has-text-centered">
                <div>
                    <p class="heading">Files</p>
                    <p class="title is-6"><?= $statistics['totalFiles'] ?></p>
                </div>
            </div>
            <div class="level-item has-text-centered">
                <div>
                    <p class="heading">Folders</p>
                    <p class="title is-6"><?= $statistics['totalDirectories'] ?></p>
                </div>
            </div>
            <div class="level-item has-text-centered">
                <div>
                    <p class="heading">Full Size</p>
                    <p class="title is-6"><?= $statistics['totalSizeFormatted'] ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Copy/Move Modal -->
<?php require __DIR__ . '/../modals/copy_move_modal.php'; ?>

    <!-- Context Menu -->
    <div id="contextMenu" class="context-menu">
        <div class="context-menu-item" onclick="contextMenuAction('open')">
            <i class="fas fa-folder-open"></i> Open
        </div>
        <?php if ($permissions->can('rename')): ?>
            <div class="context-menu-item" onclick="contextMenuAction('rename')">
                <i class="fas fa-edit"></i> Rename
            </div>
        <?php endif; ?>
        <?php if ($permissions->can('copy')): ?>
            <div class="context-menu-item" onclick="contextMenuAction('copy')">
                <i class="fas fa-copy"></i> Copy
            </div>
        <?php endif; ?>
        <?php if ($permissions->can('move')): ?>
            <div class="context-menu-item" onclick="contextMenuAction('cut')">
                <i class="fas fa-cut"></i> Cut
            </div>
        <?php endif; ?>
        <?php if ($permissions->can('copy') || $permissions->can('move')): ?>
            <div class="context-menu-item" onclick="contextMenuAction('paste')" id="contextPaste">
                <i class="fas fa-paste"></i> Paste
            </div>
        <?php endif; ?>
        <div class="context-menu-divider"></div>
        <?php if ($permissions->can('download')): ?>
            <div class="context-menu-item" onclick="contextMenuAction('download')">
                <i class="fas fa-download"></i> Download
            </div>
        <?php endif; ?>
        <?php if ($permissions->can('permissions')): ?>
            <div class="context-menu-item" onclick="contextMenuAction('permissions')">
                <i class="fas fa-lock"></i> Permissions
            </div>
        <?php endif; ?>
        <?php if ($permissions->can('delete')): ?>
            <div class="context-menu-divider"></div>
            <div class="context-menu-item" onclick="contextMenuAction('delete')">
                <i class="fas fa-trash"></i> Delete
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Modal with Dropzone -->
    <div id="uploadModal" class="modal">
        <div class="modal-background" onclick="closeUploadModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">
                    <i class="fas fa-upload mr-2"></i>
                    Upload Files
                </p>
                <button class="delete" aria-label="close" onclick="closeUploadModal()"></button>
            </header>
            <section class="modal-card-body">
                <div id="dropzoneUpload" class="dropzone">
                    <div class="dz-message" data-dz-message>
                        <div class="has-text-centered">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3 has-text-primary"></i>
                            <p class="is-size-5 mb-2"><strong>Drop files here or click to upload</strong></p>
                            <p class="has-text-grey">You can upload multiple files at once</p>
                        </div>
                    </div>
                </div>
                <div class="notification is-info is-light mt-4">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Tip:</strong> You can drag and drop multiple files directly into the upload zone
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button" onclick="closeUploadModal()">Close</button>
            </footer>
        </div>
    </div>

    <!-- New Folder Modal -->
    <div id="newFolderModal" class="modal">
        <div class="modal-background" onclick="closeNewFolderModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">Create New Folder</p>
                <button class="delete" aria-label="close" onclick="closeNewFolderModal()"></button>
            </header>
            <form method="GET" action="">
                <input type="hidden" name="action" value="new">
                <input type="hidden" name="p" value="<?= Validator::escape($currentPath) ?>">
                <section class="modal-card-body">
                    <div class="field">
                        <label class="label">Folder Name</label>
                        <div class="control">
                            <input class="input" type="text" name="name" placeholder="New Folder" required>
                        </div>
                    </div>
                </section>
                <footer class="modal-card-foot">
                    <button type="submit" class="button is-primary">Create</button>
                    <button type="button" class="button" onclick="closeNewFolderModal()">Cancel</button>
                </footer>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-background" onclick="closeDeleteModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head has-background-danger">
                <p class="modal-card-title has-text-white">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Confirm Delete
                </p>
                <button class="delete" aria-label="close" onclick="closeDeleteModal()"></button>
            </header>
            <section class="modal-card-body">
                <!-- Single item delete message -->
                <div id="deleteSingleMessage">
                    <p class="mb-4">Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
                </div>
                <!-- Multiple items delete message -->
                <div id="deleteMultipleMessage" style="display: none;">
                    <p class="mb-4">Are you sure you want to delete <strong id="deleteItemCount"></strong> items?</p>
                    <div class="box" style="max-height: 200px; overflow-y: auto;">
                        <ul id="deleteItemList" class="ml-4" style="list-style: disc;"></ul>
                    </div>
                </div>
                <div class="notification is-warning is-light mt-4">
                    <i class="fas fa-info-circle mr-2"></i>
                    This action cannot be undone.
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-danger mr-2" onclick="confirmDelete()">
                    <i class="fas fa-trash mr-2"></i>
                    Delete
                </button>
                <button class="button" onclick="closeDeleteModal()">Cancel</button>
            </footer>
        </div>
    </div>

    <!-- Move Confirmation Modal (for drag-drop) -->
    <div id="moveModal" class="modal">
        <div class="modal-background" onclick="closeMoveModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head has-background-warning">
                <p class="modal-card-title has-text-dark">
                    <i class="fas fa-arrows-alt mr-2"></i>
                    Confirm Move
                </p>
                <button class="delete" aria-label="close" onclick="closeMoveModal()"></button>
            </header>
            <section class="modal-card-body">
                <!-- Single item move message -->
                <div id="moveSingleMessage">
                    <p class="mb-4">Move <strong id="moveItemName"></strong> to <strong id="moveDestinationSingle"></strong>?</p>
                </div>
                <!-- Multiple items move message -->
                <div id="moveMultipleMessage" style="display: none;">
                    <p class="mb-4">Move <strong id="moveItemCount"></strong> items to <strong id="moveDestination"></strong>?</p>
                    <div class="box" style="max-height: 200px; overflow-y: auto;">
                        <ul id="moveItemList" class="ml-4" style="list-style: disc;"></ul>
                    </div>
                </div>
                <div class="notification is-info is-light mt-4">
                    <i class="fas fa-info-circle mr-2"></i>
                    Files will be moved to the destination folder.
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-warning mr-2" onclick="confirmMove()">
                    <i class="fas fa-arrows-alt mr-2"></i>
                    Move
                </button>
                <button class="button" onclick="closeMoveModal()">Cancel</button>
            </footer>
        </div>
    </div>

    <!-- Rename Modal -->
    <div id="renameModal" class="modal">
        <div class="modal-background" onclick="closeRenameModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">
                    <i class="fas fa-edit mr-2"></i>
                    Rename Item
                </p>
                <button class="delete" aria-label="close" onclick="closeRenameModal()"></button>
            </header>
            <section class="modal-card-body">
                <div class="field">
                    <label class="label">Current Name</label>
                    <div class="control">
                        <input class="input is-static" type="text" id="renameOldName" readonly>
                    </div>
                </div>
                <div class="field">
                    <label class="label">New Name</label>
                    <div class="control has-icons-left">
                        <input class="input" type="text" id="renameNewName" placeholder="Enter new name" required>
                        <span class="icon is-left">
                        <i class="fas fa-edit"></i>
                    </span>
                    </div>
                    <p class="help">Enter the new name for this item</p>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-primary mr-2" onclick="confirmRename()">
                    <i class="fas fa-check mr-2"></i>
                    Rename
                </button>
                <button class="button" onclick="closeRenameModal()">Cancel</button>
            </footer>
        </div>
    </div>

    <!-- Compress Modal -->
    <div id="compressModal" class="modal">
        <div class="modal-background" onclick="closeCompressModal()"></div>
        <div class="modal-card">
            <form method="POST" action="?action=zip" id="compressForm">
                <input type="hidden" name="p" value="<?= Validator::escape($currentPath) ?>">

                <header class="modal-card-head has-background-warning">
                    <p class="modal-card-title has-text-dark">
                        <i class="fas fa-file-archive mr-2"></i>
                        Create Archive
                    </p>
                    <button type="button" class="delete" aria-label="close" onclick="closeCompressModal()"></button>
                </header>
                <section class="modal-card-body">
                    <div class="field">
                        <label class="label">Archive Name</label>
                        <div class="control">
                            <input class="input" type="text" name="zipname" id="zipName" placeholder="archive" required>
                        </div>
                        <p class="help">.zip extension will be added automatically</p>
                    </div>

                    <div class="field">
                        <label class="label">Selected Items</label>
                        <div class="control">
                            <div class="box has-background-light" id="selectedItemsList">
                                <p class="has-text-grey">No items selected</p>
                            </div>
                        </div>
                    </div>
                </section>
                <footer class="modal-card-foot">
                    <button type="button" class="button is-warning mr-2" onclick="submitCompressForm()">
                        <i class="fas fa-file-archive mr-2"></i>
                        Create Archive
                    </button>
                    <button type="button" class="button" onclick="closeCompressModal()">Cancel</button>
                </footer>
            </form>
        </div>
    </div>

    <!-- Permissions Modal -->
    <div id="permissionsModal" class="modal">
        <div class="modal-background" onclick="closePermissionsModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">
                    <i class="fas fa-lock mr-2"></i>
                    Change Permissions
                </p>
                <button class="delete" aria-label="close" onclick="closePermissionsModal()"></button>
            </header>
            <section class="modal-card-body">
                <div class="field">
                    <label class="label">File/Folder</label>
                    <div class="control">
                        <input class="input is-static" type="text" id="permissionsItemName" readonly>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Current Permissions</label>
                    <div class="control">
                        <div class="tags has-addons">
                            <span class="tag is-dark" id="currentPermissionsOctal">0755</span>
                            <span class="tag is-info" id="currentPermissionsSymbolic">rwxr-xr-x</span>
                        </div>
                    </div>
                </div>

                <div class="notification is-info is-light">
                    <p class="mb-3"><strong>Permission Structure:</strong></p>
                    <div class="content">
                        <ul class="mb-0">
                            <li><strong>Owner:</strong> The user who owns the file</li>
                            <li><strong>Group:</strong> Users in the file's group</li>
                            <li><strong>Others:</strong> Everyone else</li>
                        </ul>
                    </div>
                </div>

                <div class="columns">
                    <div class="column">
                        <label class="label">Owner</label>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="ownerRead" onchange="updatePermissionPreview()">
                                Read (4)
                            </label>
                        </div>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="ownerWrite" onchange="updatePermissionPreview()">
                                Write (2)
                            </label>
                        </div>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="ownerExecute" onchange="updatePermissionPreview()">
                                Execute (1)
                            </label>
                        </div>
                    </div>

                    <div class="column">
                        <label class="label">Group</label>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="groupRead" onchange="updatePermissionPreview()">
                                Read (4)
                            </label>
                        </div>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="groupWrite" onchange="updatePermissionPreview()">
                                Write (2)
                            </label>
                        </div>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="groupExecute" onchange="updatePermissionPreview()">
                                Execute (1)
                            </label>
                        </div>
                    </div>

                    <div class="column">
                        <label class="label">Others</label>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="othersRead" onchange="updatePermissionPreview()">
                                Read (4)
                            </label>
                        </div>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="othersWrite" onchange="updatePermissionPreview()">
                                Write (2)
                            </label>
                        </div>
                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" id="othersExecute" onchange="updatePermissionPreview()">
                                Execute (1)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label">New Permissions Preview</label>
                    <div class="control">
                        <div class="tags has-addons">
                            <span class="tag is-dark" id="newPermissionsOctal">0755</span>
                            <span class="tag is-success" id="newPermissionsSymbolic">rwxr-xr-x</span>
                        </div>
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-primary mr-2" onclick="confirmPermissionChange()">
                    <i class="fas fa-check mr-2"></i>
                    Apply Changes
                </button>
                <button class="button" onclick="closePermissionsModal()">Cancel</button>
            </footer>
        </div>
    </div>

    <!-- Extract Modal -->
    <div id="extractModal" class="modal">
        <div class="modal-background" onclick="closeExtractModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">
                    <i class="fas fa-box-open mr-2"></i>
                    Extract Archive
                </p>
                <button class="delete" aria-label="close" onclick="closeExtractModal()"></button>
            </header>
            <section class="modal-card-body">
                <p>Are you sure you want to extract <strong id="extractFileName"></strong>?</p>
                <div class="field mt-4">
                    <label class="label">Destination Folder Name</label>
                    <div class="control">
                        <input class="input" type="text" id="extractFolderName" placeholder="Folder Name">
                    </div>
                    <p class="help">Leave empty to extract into current directory</p>
                </div>
                <input type="hidden" id="extractFileOrigin">
            </section>
            <footer class="modal-card-foot">
                <button class="button is-primary mr-2" onclick="confirmExtract()">
                    <i class="fas fa-check mr-2"></i>
                    Extract
                </button>
                <button class="button" onclick="closeExtractModal()">Cancel</button>
            </footer>
        </div>
    </div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
?>