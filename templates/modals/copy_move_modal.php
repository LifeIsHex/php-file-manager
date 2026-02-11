<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: copy_move_modal.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:30:41 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */
?>

<!-- Copy/Move Modal -->
<div id="copyMoveModal" class="modal">
    <div class="modal-background" onclick="closeCopyMoveModal()"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span id="copyMoveTitle">Copy / Move</span>
            </p>
            <button class="delete" aria-label="close" onclick="closeCopyMoveModal()"></button>
        </header>
        <section class="modal-card-body">
            <div class="field">
                <label class="label">Operation</label>
                <div class="control">
                    <label class="radio">
                        <input type="radio" name="operation" value="copy" checked>
                        Copy
                    </label>
                    <label class="radio">
                        <input type="radio" name="operation" value="move">
                        Move
                    </label>
                </div>
            </div>

            <div class="field">
                <label class="label">Source:</label>
                <p class="control">
                    <span id="copyMoveSource" class="has-text-weight-bold"></span>
                </p>
            </div>

            <div class="field">
                <label class="label">Destination Folder:</label>
                <div class="box has-background-light" style="max-height: 300px; overflow-y: auto;">
                    <div id="folderTreeContainer">
                        <button class="button is-small is-fullwidth mb-2" onclick="selectRootAsDestination()">
                            <i class="fas fa-home mr-2"></i> Root Directory
                        </button>
                        <div id="folderTree">
                            <!-- Folder tree will be loaded here -->
                        </div>
                    </div>
                </div>
                <p class="help">Selected: <span id="selectedDestination" class="has-text-weight-bold">/</span></p>
            </div>
        </section>
        <footer class="modal-card-foot">
            <button class="button is-success mr-2" onclick="performCopyMove()">
                <i class="fas fa-check mr-2"></i> Execute
            </button>
            <button class="button" onclick="closeCopyMoveModal()">Cancel</button>
        </footer>
    </div>
</div>