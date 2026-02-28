/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: app.js
 *
 * Last Modified: Sat, 28 Feb 2026 - 15:05:44 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

// File Manager JavaScript

// ============================================
// CSRF TOKEN HELPER
// ============================================
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Submit a POST form with CSRF token (used for state-changing actions)
function submitPostForm(action, params) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?action=' + action;

    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = getCsrfToken();
    form.appendChild(csrfInput);

    // Add other params
    for (const [key, value] of Object.entries(params)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
}

// Helper: build headers for AJAX requests (includes CSRF)
function ajaxHeaders(contentType = 'application/json') {
    return {
        'Content-Type': contentType,
        'X-CSRF-Token': getCsrfToken()
    };
}

// Mobile navbar toggle
document.addEventListener('DOMContentLoaded', () => {
    // Get all navbar burgers
    const navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    navbarBurgers.forEach(el => {
        el.addEventListener('click', () => {
            const target = el.dataset.target;
            const targetEl = document.getElementById(target);

            el.classList.toggle('is-active');
            targetEl.classList.toggle('is-active');
        });
    });

    // Auto-hide notifications
    const notifications = document.querySelectorAll('.notification .delete');
    notifications.forEach(button => {
        button.addEventListener('click', () => {
            button.parentElement.remove();
        });

        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (button.parentElement) {
                button.parentElement.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => button.parentElement.remove(), 300);
            }
        }, 5000);
    });

    // Select all checkbox
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', (e) => {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            updateSelectionUI();
        });
    }

    // Add change listeners to all item checkboxes
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    itemCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectionUI);
    });

    // Initial check for selection state
    updateSelectionUI();

    // Search functionality (AJAX)
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();

            // Clear previous timeout
            clearTimeout(searchTimeout);

            // If query is empty, reload to show all rows
            if (!query) {
                // If query is empty, clear search results and show all rows (by reloading or re-fetching original data)
                // For now, we'll reload to ensure the full list is displayed.
                window.location.reload();
                return;
            }

            // Debounce search for better performance
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });
    }
});

// Dropzone configuration
Dropzone.autoDiscover = false;
let dropzoneInstance = null;

// Initialize Dropzone when modal is opened
function initializeDropzone() {
    if (dropzoneInstance) {
        return; // Already initialized
    }

    const params = new URLSearchParams(window.location.search);
    const currentPath = params.get('p') || '';

    dropzoneInstance = new Dropzone('#dropzoneUpload', {
        url: '?action=upload',
        paramName: 'upload',
        maxFilesize: 50, // MB
        parallelUploads: 5,
        // Note: uploadMultiple cannot be used with chunking, so we use single file uploads
        // but allow multiple files to be queued
        uploadMultiple: false,
        autoProcessQueue: true,
        addRemoveLinks: true,
        dictDefaultMessage: 'Drop files here or click to upload',
        dictFallbackMessage: 'Your browser does not support drag and drop file uploads.',
        dictFileTooBig: 'File is too big ({{filesize}}MB). Max filesize: {{maxFilesize}}MB.',
        dictInvalidFileType: 'You can\'t upload files of this type.',
        dictRemoveFile: 'Remove',
        dictCancelUpload: 'Cancel upload',
        dictCancelUploadConfirmation: 'Are you sure you want to cancel this upload?',

        // Chunked upload configuration
        chunking: true,
        forceChunking: true,
        chunkSize: 1048576, // 1MB - matches config chunk_size
        parallelChunkUploads: false,

        init: function () {
            this.on('sending', function (file, xhr, formData) {
                const params = new URLSearchParams(window.location.search);
                formData.append('p', params.get('p') || '');
            });

            this.on('success', function (file, response) {
                console.log('Upload successful:', file.name);
            });

            this.on('error', function (file, errorMessage) {
                console.error('Upload error:', errorMessage);
            });

            this.on('queuecomplete', function () {
                console.log('All uploads completed');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            });
        }
    });
}

// Perform AJAX search
function performSearch(query) {
    const params = new URLSearchParams(window.location.search);
    const currentPath = params.get('p') || '';

    // Show loading indicator
    const searchInput = document.getElementById('searchInput');
    searchInput.classList.add('is-loading');

    fetch(`?action=search&q=${encodeURIComponent(query)}&p=${encodeURIComponent(currentPath)}`)
        .then(response => response.json())
        .then(data => {
            updateSearchResults(data, query);
        })
        .catch(error => {
            console.error('Search error:', error);
        })
        .finally(() => {
            searchInput.classList.remove('is-loading');
        });
}

// Update the table with search results
function updateSearchResults(data, query) {
    const tbody = document.querySelector('.table tbody');
    if (!tbody) return;

    // Clear current results (except parent directory link)
    const rows = tbody.querySelectorAll('.file-row');
    rows.forEach(row => row.remove());

    // Check if there are results
    if (data.directories.length === 0 && data.files.length === 0) {
        const noResultsRow = document.createElement('tr');
        noResultsRow.className = 'file-row';
        const cols = (window.FM_COLUMNS && window.FM_COLUMNS.total) ? window.FM_COLUMNS.total : 7;
        noResultsRow.innerHTML = `
            <td colspan="${cols}" class="has-text-centered has-text-grey">
                <i class="fas fa-search fa-2x mb-3"></i>
                <p>No results found for "${query}"</p>
            </td>
        `;
        tbody.appendChild(noResultsRow);
        return;
    }

    // Add directory results
    data.directories.forEach(dir => {
        const row = createSearchResultRow(dir, 'directory');
        tbody.appendChild(row);
    });

    // Add file results
    data.files.forEach(file => {
        const row = createSearchResultRow(file, 'file');
        tbody.appendChild(row);
    });
}

// Modal functions
function showUploadModal() {
    initializeDropzone();
    document.getElementById('uploadModal').classList.add('is-active');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('is-active');
    // Reset dropzone if it exists
    if (dropzoneInstance) {
        dropzoneInstance.removeAllFiles();
    }
}

function showNewFolderModal() {
    document.getElementById('newFolderModal').classList.add('is-active');
}

function closeNewFolderModal() {
    document.getElementById('newFolderModal').classList.remove('is-active');
}

// Update file name display
function updateFileName(input) {
    const fileNameDisplay = document.getElementById('fileName');
    if (input.files.length > 0) {
        if (input.files.length === 1) {
            fileNameDisplay.textContent = input.files[0].name;
        } else {
            fileNameDisplay.textContent = `${input.files.length} files selected`;
        }
    } else {
        fileNameDisplay.textContent = 'No file selected';
    }
}

// Delete item - calls the unified showDeleteModal function defined below
function deleteItem(name) {
    // This will use the showDeleteModal function from the multi-select system
    if (typeof showDeleteModal === 'function') {
        showDeleteModal(name);
    } else {
        // Fallback for old behavior
        const params = new URLSearchParams(window.location.search);
        const currentPath = params.get('p') || '';
        window.location.href = `?action=delete&p=${encodeURIComponent(currentPath)}&name=${encodeURIComponent(name)}`;
    }
}

// Rename item - State variables
let renameOldName = '';

function renameItem(oldName) {
    renameOldName = oldName;
    document.getElementById('renameOldName').value = oldName;
    document.getElementById('renameNewName').value = oldName;
    document.getElementById('renameModal').classList.add('is-active');

    // Focus on the new name input
    setTimeout(() => {
        const input = document.getElementById('renameNewName');
        input.focus();
        input.select();
    }, 100);
}

function closeRenameModal() {
    document.getElementById('renameModal').classList.remove('is-active');
    renameOldName = '';
    document.getElementById('renameNewName').value = '';
}

function confirmRename() {
    const newName = document.getElementById('renameNewName').value.trim();

    if (!newName) {
        showToast('Please enter a new name', 'warning');
        return;
    }

    if (newName === renameOldName) {
        closeRenameModal();
        return;
    }

    const currentPath = getCurrentPath();
    submitPostForm('rename', {
        p: currentPath,
        old: renameOldName,
        new: newName
    });
}

// Allow Enter key to submit rename
document.addEventListener('DOMContentLoaded', () => {
    const renameInput = document.getElementById('renameNewName');
    if (renameInput) {
        renameInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmRename();
            }
        });
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + U for upload
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
        e.preventDefault();
        if (document.getElementById('uploadModal')) {
            showUploadModal();
        }
    }

    // Ctrl/Cmd + N for new folder
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        if (document.getElementById('newFolderModal')) {
            showNewFolderModal();
        }
    }

    // Escape to close modals
    if (e.key === 'Escape') {
        closeUploadModal();
        closeNewFolderModal();
        closeDeleteModal();
        closeRenameModal();
    }
});

// Fade out animation for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-10px); }
    }
`;
document.head.appendChild(style);

// Helper function to create search result rows
function createSearchResultRow(item, type) {
    const row = document.createElement('tr');
    row.className = 'file-row';
    row.dataset.name = item.name;

    const params = new URLSearchParams(window.location.search);
    const currentPath = params.get('p') || '';

    // Read column visibility from the PHP-emitted config (default all visible)
    const showCols = window.FM_COLUMNS || {size: true, owner: true, modified: true, permissions: true};

    let checkbox = `<td><input type="checkbox" class="file-checkbox" data-name="${escapeHtml(item.name)}"></td>`;

    let nameCell;
    if (type === 'directory') {
        const dirPath = item.path ? item.path + '/' + item.name : item.name;
        nameCell = `
            <td>
                <a href="?p=${encodeURIComponent(dirPath)}" class="has-text-link">
                    <i class="fas ${item.icon} mr-2 has-text-info"></i>
                    <strong>${escapeHtml(item.name)}</strong>
                </a>
                ${item.path ? `<br><small class="has-text-grey">in: ${escapeHtml(item.path)}</small>` : ''}
            </td>
        `;
    } else {
        nameCell = `
            <td>
                <i class="fas ${item.icon} mr-2"></i>
                ${escapeHtml(item.name)}
                ${item.path ? `<br><small class="has-text-grey">in: ${escapeHtml(item.path)}</small>` : ''}
            </td>
        `;
    }

    const sizeCell = showCols.size ? (type === 'directory' ? '<td>-</td>' : `<td>${item.size_formatted}</td>`) : '';
    const ownerCell = showCols.owner ? `<td class="has-text-grey">${escapeHtml(item.owner || 'unknown')}</td>` : '';
    const modifiedCell = showCols.modified ? `<td>${formatDate(item.modified)}</td>` : '';
    const permissionsCell = showCols.permissions ? `<td class="has-text-grey is-family-monospace is-size-7">${item.permissions}</td>` : '';

    let actionsCell;
    if (type === 'directory') {
        actionsCell = `
            <td>
                <div class="buttons are-small">
                    <button class="button is-warning" onclick="renameItem('${escapeHtml(item.name)}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="button is-danger" onclick="deleteItem('${escapeHtml(item.name)}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
    } else {
        const downloadUrl = `?action=download&p=${encodeURIComponent(item.path)}&file=${encodeURIComponent(item.name)}`;
        actionsCell = `
            <td>
                <div class="buttons are-small">
                    <a href="${downloadUrl}" class="button is-info">
                        <i class="fas fa-download"></i>
                    </a>
                    <button class="button is-warning" onclick="renameItem('${escapeHtml(item.name)}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="button is-danger" onclick="deleteItem('${escapeHtml(item.name)}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
    }

    row.innerHTML = checkbox + nameCell + sizeCell + ownerCell + modifiedCell + permissionsCell + actionsCell;
    return row;
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper function to format date
function formatDate(timestamp) {
    const date = new Date(timestamp * 1000);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}`;
}

// Permission Manager
let permissionsItemName = '';

function changePermissions(name, currentPerms) {
    permissionsItemName = name;
    document.getElementById('permissionsItemName').value = name;

    // Display current permissions
    document.getElementById('currentPermissionsOctal').textContent = currentPerms;
    document.getElementById('currentPermissionsSymbolic').textContent = octalToSymbolic(currentPerms);

    // Set checkboxes based on current permissions
    setPermissionCheckboxes(currentPerms);

    // Update preview
    updatePermissionPreview();

    // Show modal
    document.getElementById('permissionsModal').classList.add('is-active');
}

function closePermissionsModal() {
    document.getElementById('permissionsModal').classList.remove('is-active');
    permissionsItemName = '';
}

function setPermissionCheckboxes(octal) {
    // Remove leading 0 if present
    const perms = octal.toString().replace(/^0+/, '');

    if (perms.length >= 3) {
        const owner = parseInt(perms[perms.length - 3]);
        const group = parseInt(perms[perms.length - 2]);
        const others = parseInt(perms[perms.length - 1]);

        // Owner
        document.getElementById('ownerRead').checked = (owner & 4) !== 0;
        document.getElementById('ownerWrite').checked = (owner & 2) !== 0;
        document.getElementById('ownerExecute').checked = (owner & 1) !== 0;

        // Group
        document.getElementById('groupRead').checked = (group & 4) !== 0;
        document.getElementById('groupWrite').checked = (group & 2) !== 0;
        document.getElementById('groupExecute').checked = (group & 1) !== 0;

        // Others
        document.getElementById('othersRead').checked = (others & 4) !== 0;
        document.getElementById('othersWrite').checked = (others & 2) !== 0;
        document.getElementById('othersExecute').checked = (others & 1) !== 0;
    }
}

function updatePermissionPreview() {
    const owner =
        (document.getElementById('ownerRead').checked ? 4 : 0) +
        (document.getElementById('ownerWrite').checked ? 2 : 0) +
        (document.getElementById('ownerExecute').checked ? 1 : 0);

    const group =
        (document.getElementById('groupRead').checked ? 4 : 0) +
        (document.getElementById('groupWrite').checked ? 2 : 0) +
        (document.getElementById('groupExecute').checked ? 1 : 0);

    const others =
        (document.getElementById('othersRead').checked ? 4 : 0) +
        (document.getElementById('othersWrite').checked ? 2 : 0) +
        (document.getElementById('othersExecute').checked ? 1 : 0);

    const octal = `0${owner}${group}${others}`;
    const symbolic = octalToSymbolic(octal);

    document.getElementById('newPermissionsOctal').textContent = octal;
    document.getElementById('newPermissionsSymbolic').textContent = symbolic;
}

function octalToSymbolic(octal) {
    const perms = octal.toString().replace(/^0+/, '');
    if (perms.length < 3) return '?????????';

    const owner = parseInt(perms[perms.length - 3]);
    const group = parseInt(perms[perms.length - 2]);
    const others = parseInt(perms[perms.length - 1]);

    const toSymbolic = (n) => {
        return ((n & 4) ? 'r' : '-') +
            ((n & 2) ? 'w' : '-') +
            ((n & 1) ? 'x' : '-');
    };

    return toSymbolic(owner) + toSymbolic(group) + toSymbolic(others);
}

function confirmPermissionChange() {
    const newPerms = document.getElementById('newPermissionsOctal').textContent;
    const currentPath = getCurrentPath();

    // Send AJAX request with CSRF
    fetch('?action=chmod', {
        method: 'POST',
        headers: ajaxHeaders('application/x-www-form-urlencoded'),
        body: `p=${encodeURIComponent(currentPath)}&name=${encodeURIComponent(permissionsItemName)}&mode=${encodeURIComponent(newPerms)}&csrf_token=${encodeURIComponent(getCsrfToken())}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closePermissionsModal();
                window.location.reload();
            } else {
                showToast('Failed to change permissions: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Permission change error:', error);
            showToast('Failed to change permissions', 'error');
        });
}

// Compress/Archive functions
function showCompressModal() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const selectedItems = Array.from(checkboxes).map(cb => cb.value);

    if (selectedItems.length === 0) {
        showToast('Please select at least one file or folder to compress', 'error');
        return;
    }

    // Display selected items
    const listDiv = document.getElementById('selectedItemsList');
    listDiv.innerHTML = selectedItems.map(item =>
        `<span class="tag is-warning is-medium mr-2 mb-2">${escapeHtml(item)}</span>`
    ).join('');

    // Set default archive name
    if (selectedItems.length === 1) {
        document.getElementById('zipName').value = selectedItems[0];
    } else {
        document.getElementById('zipName').value = 'archive';
    }

    document.getElementById('compressModal').classList.add('is-active');
}

function closeCompressModal() {
    document.getElementById('compressModal').classList.remove('is-active');
}

// Extract Modal Functions
function showExtractModal(fileName) {
    document.getElementById('extractFileName').textContent = fileName;
    document.getElementById('extractFileOrigin').value = fileName;

    // Default folder name is filename without extension
    const folderName = fileName.replace(/\.[^/.]+$/, "");
    document.getElementById('extractFolderName').value = folderName;

    document.getElementById('extractModal').classList.add('is-active');

    // Focus on folder name input
    setTimeout(() => {
        const input = document.getElementById('extractFolderName');
        input.focus();
        input.select();
    }, 100);
}

function closeExtractModal() {
    document.getElementById('extractModal').classList.remove('is-active');
    document.getElementById('extractFileName').textContent = '';
    document.getElementById('extractFileOrigin').value = '';
    document.getElementById('extractFolderName').value = '';
}

function confirmExtract() {
    const fileName = document.getElementById('extractFileOrigin').value;
    const folderName = document.getElementById('extractFolderName').value.trim();

    if (!fileName) return;

    const currentPath = getCurrentPath();
    const params = {p: currentPath, file: fileName};
    if (folderName) {
        params.target_folder = folderName;
    }
    submitPostForm('extract', params);
}

// Allow Enter key to submit extract
document.addEventListener('DOMContentLoaded', () => {
    const extractInput = document.getElementById('extractFolderName');
    if (extractInput) {
        extractInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmExtract();
            }
        });
    }
});

// Checkbox selection functions
function toggleSelectAll(checkbox) {
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    itemCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}


// Table sorting functionality
let sortDirection = {};

function sortTable(columnIndex) {
    const table = document.getElementById('fileTable');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const allRows = Array.from(tbody.querySelectorAll('tr'));

    // Separate parent directory row from other rows
    // Parent directory row has ".. (Parent Directory)" text
    const parentRow = allRows.find(row => {
        const cells = row.querySelectorAll('td');
        for (let cell of cells) {
            if (cell.textContent.includes('.. (Parent Directory)')) {
                return true;
            }
        }
        return false;
    });

    // Get only sortable rows (excluding parent directory)
    const rows = allRows.filter(row => row !== parentRow);
    // Toggle sort direction
    if (!sortDirection[columnIndex]) {
        sortDirection[columnIndex] = 'asc';
    } else {
        sortDirection[columnIndex] = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
    }

    const direction = sortDirection[columnIndex];

    // Sort rows
    rows.sort((a, b) => {
        let aValue = a.cells[columnIndex].textContent.trim();
        let bValue = b.cells[columnIndex].textContent.trim();

        // Handle size sorting (convert to bytes)
        if (columnIndex === 2) { // Size column
            aValue = parseSizeToBytes(aValue);
            bValue = parseSizeToBytes(bValue);
        }

        // Handle date sorting
        if (columnIndex === 4) { // Modified column
            aValue = new Date(aValue).getTime() || 0;
            bValue = new Date(bValue).getTime() || 0;
        }

        // Numeric comparison or string comparison
        if (typeof aValue === 'number' && typeof bValue === 'number') {
            return direction === 'asc' ? aValue - bValue : bValue - aValue;
        } else {
            return direction === 'asc'
                ? aValue.localeCompare(bValue, undefined, {numeric: true})
                : bValue.localeCompare(aValue, undefined, {numeric: true});
        }
    });

    // Re-append rows: parent directory first, then sorted rows
    if (parentRow) {
        tbody.appendChild(parentRow);
    }
    rows.forEach(row => tbody.appendChild(row));

    // Update header indicators
    updateSortIndicators(columnIndex, direction);
}

function parseSizeToBytes(sizeStr) {
    if (sizeStr === '-' || sizeStr === '') return 0;

    const units = {
        'B': 1,
        'KB': 1024,
        'MB': 1024 * 1024,
        'GB': 1024 * 1024 * 1024
    };

    const match = sizeStr.match(/^([\d.]+)\s*(\w+)$/);
    if (!match) return 0;

    const value = parseFloat(match[1]);
    const unit = match[2];

    return value * (units[unit] || 1);
}

function updateSortIndicators(activeColumn, direction) {
    const headers = document.querySelectorAll('#fileTable th[data-sortable]');
    headers.forEach((header, index) => {
        const columnIndex = index + 1;
        const icon = header.querySelector('.sort-icon');

        // Remove existing icon
        if (icon) {
            icon.remove();
        }

        // Add icon to active column
        if (columnIndex === activeColumn) {
            const newIcon = document.createElement('i');
            newIcon.className = `fas fa-sort-${direction === 'asc' ? 'up' : 'down'} ml-2 sort-icon`;
            header.appendChild(newIcon);
        }
    });
}

// Initialize table sorting on page load
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('fileTable');
    if (table) {
        const headers = table.querySelectorAll('th[data-sortable]');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => sortTable(index + 1)); // +1 to skip checkbox column
        });
    }
});

// Copy/Move Modal Functions
let currentCopyMoveItem = '';
let currentCopyMovePath = '';
let selectedDestination = '';

function showCopyMoveModal(itemName, operation) {
    currentCopyMoveItem = itemName;
    currentCopyMovePath = getCurrentPath();
    selectedDestination = '';

    // Set operation radio button
    document.querySelector(`input[name="operation"][value="${operation}"]`).checked = true;

    // Set source display
    document.getElementById('copyMoveSource').textContent = itemName;

    // Update modal title
    const title = operation === 'copy' ? 'Copy File/Folder' : 'Move File/Folder';
    document.getElementById('copyMoveTitle').textContent = title;

    // Load folder tree for current directory
    loadFolderTree('');

    // Reset and show selected destination
    selectRootAsDestination();

    // Show modal
    document.getElementById('copyMoveModal').classList.add('is-active');
}

function closeCopyMoveModal() {
    document.getElementById('copyMoveModal').classList.remove('is-active');
    currentCopyMoveItem = '';
    selectedDestination = '';
}

function selectRootAsDestination() {
    selectedDestination = '';
    document.getElementById('selectedDestination').textContent = '/ (Root)';

    // Remove active class from all folder items
    document.querySelectorAll('.folder-tree-item').forEach(item => {
        item.classList.remove('has-background-info', 'has-text-white');
    });
}

function loadFolderTree(path) {
    const container = document.getElementById('folderTree');

    fetch(`?action=folder-tree&p=${encodeURIComponent(path)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.folders.length === 0) {
                    container.innerHTML = '<p class="has-text-grey-light">No subfolders</p>';
                } else {
                    let html = '<ul class="menu-list">';
                    data.folders.forEach(folder => {
                        html += `
                            <li>
                                <a class="folder-tree-item" 
                                   data-path="${escapeHtml(folder.path)}"
                                   onclick="selectDestinationFolder('${escapeHtml(folder.path).replace(/'/g, "\\'")}')"
                                   ondblclick="expandFolder('${escapeHtml(folder.path).replace(/'/g, "\\'")}')">
                                    <i class="fas fa-folder mr-2"></i>
                                    ${escapeHtml(folder.name)}
                                </a>
                            </li>`;
                    });
                    html += '</ul>';
                    container.innerHTML = html;
                }
            }
        })
        .catch(error => {
            console.error('Error loading folder tree:', error);
            container.innerHTML = '<p class="has-text-danger">Error loading folders</p>';
        });
}

function selectDestinationFolder(path) {
    selectedDestination = path;
    document.getElementById('selectedDestination').textContent = '/' + path;

    // Highlight selected folder
    document.querySelectorAll('.folder-tree-item').forEach(item => {
        if (item.getAttribute('data-path') === path) {
            item.classList.add('has-background-info', 'has-text-white');
        } else {
            item.classList.remove('has-background-info', 'has-text-white');
        }
    });
}

function expandFolder(path) {
    // Load subfolders for this path
    loadFolderTree(path);
    selectDestinationFolder(path);
}

function performCopyMove() {
    const operation = document.querySelector('input[name="operation"]:checked').value;
    const sourcePath = currentCopyMovePath ? currentCopyMovePath + '/' + currentCopyMoveItem : currentCopyMoveItem;

    if (selectedDestination === sourcePath) {
        showToast('Cannot copy/move to the same location', 'warning');
        return;
    }

    submitPostForm(operation, {
        source: sourcePath,
        destination: selectedDestination,
        currentPath: currentCopyMovePath
    });
}

function getCurrentPath() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('p') || '';
}

// ============================================
// DRAG AND DROP FUNCTIONALITY (with modal + multi-select)
// ============================================
let sortableInstance = null;
let pendingMoveItems = [];
let pendingMoveDestination = '';
let currentDropTarget = null; // Track the folder being hovered over

function initDragAndDrop() {
    const tbody = document.querySelector('#fileTable tbody');
    if (!tbody) return;

    sortableInstance = new Sortable(tbody, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        handle: '.file-row, .directory-row',
        filter: '.parent-directory',
        preventOnFilter: true,  // Prevent any action on filtered items
        // Keep parent directory row fixed at top - don't allow items to be placed above it
        onMove: function (evt) {
            // Don't allow dropping above or on parent directory row
            if (evt.related && evt.related.classList.contains('parent-directory')) {
                return false; // Cancel the move
            }

            // Visual feedback: highlight folder when dragging over it
            const related = evt.related;
            if (related && related.classList.contains('directory-row')) {
                // Track this as the current drop target
                currentDropTarget = related;
                // Clear previous hover styling
                document.querySelectorAll('.drop-target-hover').forEach(row => {
                    row.classList.remove('drop-target-hover');
                });
                related.classList.add('drop-target-hover');
            } else {
                // Clear drop target if not over a directory
                currentDropTarget = null;
            }

            return true; // Allow the move for other items
        },
        onStart: function (evt) {
            evt.item.classList.add('dragging');
            currentDropTarget = null; // Reset drop target
            // Highlight all folder rows as potential drop targets (except parent directory)
            document.querySelectorAll('.directory-row:not(.parent-directory)').forEach(row => {
                if (row !== evt.item) {
                    row.classList.add('drop-target-available');
                }
            });
        },
        onEnd: function (evt) {
            evt.item.classList.remove('dragging');
            // Remove all drop target highlights
            document.querySelectorAll('.drop-target-available, .drop-target-hover').forEach(row => {
                row.classList.remove('drop-target-available', 'drop-target-hover');
            });

            // Get the dragged item name
            const draggedItem = evt.item.getAttribute('data-name');

            // Use the tracked drop target from onMove, not the reordered index
            const dropTarget = currentDropTarget;
            currentDropTarget = null; // Reset for next drag

            // If no valid drop target was tracked, just reload
            if (!dropTarget || dropTarget === evt.item) {
                location.reload(); // Reset order
                return;
            }

            const targetName = dropTarget.getAttribute('data-name');
            const isTargetDirectory = dropTarget.classList.contains('directory-row');

            // Only allow drop on directories (not on files)
            if (isTargetDirectory && targetName) {
                // Check if multiple items are selected and include the dragged item
                const selectedItems = getSelectedItems();
                let itemsToMove = [];

                if (selectedItems.length > 1 && selectedItems.includes(draggedItem)) {
                    // Moving multiple selected items
                    itemsToMove = selectedItems;
                } else {
                    // Moving just the dragged item
                    itemsToMove = [draggedItem];
                }

                // Filter out the target folder from items (can't move folder into itself)
                itemsToMove = itemsToMove.filter(item => item !== targetName);

                if (itemsToMove.length > 0) {
                    showMoveModal(itemsToMove, targetName);
                } else {
                    location.reload();
                }
            } else {
                // Reset the table order (not a valid drop)
                location.reload();
            }
        }
    });
}

// Show move confirmation modal
function showMoveModal(items, destination) {
    pendingMoveItems = items;
    pendingMoveDestination = destination;

    const singleMsg = document.getElementById('moveSingleMessage');
    const multiMsg = document.getElementById('moveMultipleMessage');

    if (items.length === 1) {
        // Single item
        singleMsg.style.display = 'block';
        multiMsg.style.display = 'none';
        document.getElementById('moveItemName').textContent = items[0];
        document.getElementById('moveDestinationSingle').textContent = destination;
    } else {
        // Multiple items
        singleMsg.style.display = 'none';
        multiMsg.style.display = 'block';
        document.getElementById('moveItemCount').textContent = items.length;
        document.getElementById('moveDestination').textContent = destination;

        // Populate list
        const itemList = document.getElementById('moveItemList');
        itemList.innerHTML = '';
        items.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item;
            itemList.appendChild(li);
        });
    }

    document.getElementById('moveModal').classList.add('is-active');
}

// Close move modal
function closeMoveModal() {
    document.getElementById('moveModal').classList.remove('is-active');
    pendingMoveItems = [];
    pendingMoveDestination = '';
    // Reset the table order
    location.reload();
}

// Confirm move - perform the actual move operation
async function confirmMove() {
    if (pendingMoveItems.length === 0 || !pendingMoveDestination) {
        closeMoveModal();
        return;
    }

    // Save values before closing modal
    const itemsToMove = [...pendingMoveItems];
    const destination = pendingMoveDestination;
    const currentPath = getCurrentPath();

    // Close modal
    document.getElementById('moveModal').classList.remove('is-active');
    pendingMoveItems = [];
    pendingMoveDestination = '';

    // Determine destination path
    const destPath = currentPath ? currentPath + '/' + destination : destination;

    // Use AJAX paste endpoint for all moves (single or multiple)
    showToast('Moving ' + (itemsToMove.length === 1 ? itemsToMove[0] : itemsToMove.length + ' items') + '...', 'info');

    try {
        const response = await fetch('?action=paste', {
            method: 'POST',
            headers: ajaxHeaders(),
            body: JSON.stringify({
                items: itemsToMove,
                sourcePath: currentPath,
                destPath: destPath,
                operation: 'cut'
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast(result.message, 'success');
            // Navigate to the destination folder after successful move
            setTimeout(() => {
                window.location.href = '?p=' + encodeURIComponent(destPath);
            }, 500);
        } else {
            showToast(result.message || 'Move failed', 'error');
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (error) {
        console.error('Move error:', error);
        showToast('An error occurred while moving', 'error');
        setTimeout(() => window.location.reload(), 1000);
    }
}

// Legacy function for backwards compatibility (no longer used by drag-drop)
function performDragDropMove(sourcePath, destPath) {
    submitPostForm('move', {
        source: sourcePath,
        destination: destPath,
        currentPath: getCurrentPath()
    });
}

// Submit copy/move as POST with CSRF (called from template copy/move buttons)
function submitCopyMove(action, fileName, currentPath) {
    submitPostForm(action, {
        file: fileName,
        p: currentPath
    });
}

// ============================================
// CONTEXT MENU FUNCTIONALITY
// ============================================
let contextMenuItem = null;

// ============================================
// CLIPBOARD SYSTEM (Multi-Item Support)
// ============================================
const Clipboard = {
    STORAGE_KEY: 'fm_clipboard',

    // Get clipboard contents from sessionStorage
    get() {
        try {
            return JSON.parse(sessionStorage.getItem(this.STORAGE_KEY)) || null;
        } catch (e) {
            return null;
        }
    },

    // Set clipboard contents
    set(items, operation, sourcePath) {
        const data = {
            items: Array.isArray(items) ? items : [items],
            operation: operation, // 'copy' or 'cut'
            sourcePath: sourcePath
        };
        sessionStorage.setItem(this.STORAGE_KEY, JSON.stringify(data));
        this.updateUI();
    },

    // Clear clipboard
    clear() {
        sessionStorage.removeItem(this.STORAGE_KEY);
        this.updateUI();
    },

    // Check if clipboard has items
    hasItems() {
        const data = this.get();
        return data && data.items && data.items.length > 0;
    },

    // Update paste button visibility
    updateUI() {
        const clipboard = this.get();
        const hasItems = clipboard && clipboard.items && clipboard.items.length > 0;

        // Update toolbar paste button
        const pasteBtn = document.getElementById('pasteButton');
        if (pasteBtn) {
            pasteBtn.style.display = hasItems ? 'inline-flex' : 'none';
        }

        // Update context menu paste option
        const contextPaste = document.getElementById('contextPaste');
        if (contextPaste) {
            contextPaste.style.display = hasItems ? 'flex' : 'none';
        }
    }
};

// Get selected items from checked checkboxes
function getSelectedItems() {
    // Template uses .item-checkbox class with value attribute containing filename
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const items = [];
    checkboxes.forEach(cb => {
        if (cb.value) {
            items.push(cb.value);
        }
    });
    return items;
}

// Update selection UI (show/hide toolbar buttons and counter)
function updateSelectionUI() {
    const selectedItems = getSelectedItems();
    const count = selectedItems.length;

    // Show/hide selection action buttons
    const selectionActions = document.getElementById('selectionActions');
    if (selectionActions) {
        selectionActions.style.display = count > 0 ? 'inline' : 'none';
    }

    // Update counter text
    const counter = document.getElementById('selectionCounter');
    if (counter) {
        counter.textContent = count + ' selected';
    }
}

// Copy selected items (or single item) to clipboard
function copyToClipboard(items = null, operation = 'copy') {
    const itemsToCopy = items || getSelectedItems();
    if (itemsToCopy.length === 0) {
        showToast('No items selected', 'warning');
        return;
    }

    Clipboard.set(itemsToCopy, operation, getCurrentPath());
    showToast(`${itemsToCopy.length} item(s) ${operation === 'cut' ? 'cut' : 'copied'} to clipboard`, 'success');
}

// Paste from clipboard to current directory
async function pasteFromClipboard() {
    const clipboard = Clipboard.get();
    if (!clipboard || !clipboard.items || clipboard.items.length === 0) {
        showToast('Clipboard is empty', 'warning');
        return;
    }

    const currentPath = getCurrentPath();

    try {
        const response = await fetch('?action=paste', {
            method: 'POST',
            headers: ajaxHeaders(),
            body: JSON.stringify({
                items: clipboard.items,
                sourcePath: clipboard.sourcePath,
                destPath: currentPath,
                operation: clipboard.operation
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast(result.message, 'success');
            Clipboard.clear(); // Clear clipboard after paste
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(result.message || 'Paste failed', 'error');
            // Show failed items if any
            if (result.items) {
                const failed = result.items.filter(i => !i.success);
                if (failed.length > 0) {
                    console.error('Failed items:', failed);
                }
            }
        }
    } catch (error) {
        console.error('Paste error:', error);
        showToast('An error occurred while pasting', 'error');
    }
}

// Items pending deletion (set when modal is shown)
let pendingDeleteItems = [];
let pendingDeleteIsMultiple = false;

// Show delete confirmation modal for selected items
function deleteSelectedItems() {
    const items = getSelectedItems();
    if (items.length === 0) {
        showToast('No items selected', 'warning');
        return;
    }

    // Store items for when user confirms
    pendingDeleteItems = items;
    pendingDeleteIsMultiple = true;

    // Show appropriate message in modal
    const singleMsg = document.getElementById('deleteSingleMessage');
    const multiMsg = document.getElementById('deleteMultipleMessage');
    const itemCount = document.getElementById('deleteItemCount');
    const itemList = document.getElementById('deleteItemList');

    if (items.length === 1) {
        // Single item
        singleMsg.style.display = 'block';
        multiMsg.style.display = 'none';
        document.getElementById('deleteItemName').textContent = items[0];
    } else {
        // Multiple items
        singleMsg.style.display = 'none';
        multiMsg.style.display = 'block';
        itemCount.textContent = items.length;

        // Populate list
        itemList.innerHTML = '';
        items.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item;
            itemList.appendChild(li);
        });
    }

    // Show modal
    document.getElementById('deleteModal').classList.add('is-active');
}

// Show delete confirmation modal for a single item (called from row delete button)
function showDeleteModal(itemName) {
    pendingDeleteItems = [itemName];
    pendingDeleteIsMultiple = false;

    // Show single item message
    const singleMsg = document.getElementById('deleteSingleMessage');
    const multiMsg = document.getElementById('deleteMultipleMessage');

    singleMsg.style.display = 'block';
    multiMsg.style.display = 'none';
    document.getElementById('deleteItemName').textContent = itemName;

    // Show modal
    document.getElementById('deleteModal').classList.add('is-active');
}

// Close delete modal
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('is-active');
    pendingDeleteItems = [];
}

// Confirm delete - called when user clicks Delete button in modal
async function confirmDelete() {
    if (pendingDeleteItems.length === 0) {
        closeDeleteModal();
        return;
    }

    // IMPORTANT: Save items BEFORE closing modal, because closeDeleteModal clears pendingDeleteItems
    const itemsToDelete = [...pendingDeleteItems];
    const isMultiple = pendingDeleteIsMultiple;
    const currentPath = getCurrentPath();

    // Close modal immediately for better UX
    closeDeleteModal();

    if (itemsToDelete.length === 1 && !isMultiple) {
        // Single item delete via POST with CSRF
        submitPostForm('delete', {
            p: currentPath,
            name: itemsToDelete[0]
        });
        return;
    }

    // Multiple items - use AJAX
    try {
        const response = await fetch('?action=delete-multiple', {
            method: 'POST',
            headers: ajaxHeaders(),
            body: JSON.stringify({
                items: itemsToDelete,
                path: currentPath
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast(result.message, 'success');
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(result.message || 'Delete failed', 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showToast('An error occurred while deleting', 'error');
    }
}

// Download selected items (single file = direct download, multiple = zip)
async function downloadSelected() {
    const items = getSelectedItems();
    if (items.length === 0) {
        showToast('No items selected', 'warning');
        return;
    }

    const currentPath = getCurrentPath();

    // Single file - use direct download
    if (items.length === 1) {
        window.location.href = `?action=download&p=${encodeURIComponent(currentPath)}&file=${encodeURIComponent(items[0])}`;
        return;
    }

    // Multiple files - create zip via AJAX then trigger download
    showToast('Creating zip file...', 'info');

    try {
        const response = await fetch('?action=download-multiple', {
            method: 'POST',
            headers: ajaxHeaders(),
            body: JSON.stringify({
                items: items,
                path: currentPath
            })
        });

        // Check if response is a file (zip) or JSON (error)
        const contentType = response.headers.get('content-type');

        if (contentType && contentType.includes('application/zip')) {
            // Download the zip file
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'files_' + new Date().toISOString().slice(0, 10) + '.zip';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
            showToast(`Downloaded ${items.length} items as zip`, 'success');
        } else {
            // JSON error response
            const result = await response.json();
            showToast(result.message || 'Download failed', 'error');
        }
    } catch (error) {
        console.error('Download error:', error);
        showToast('An error occurred while creating download', 'error');
    }
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function (e) {
        // Don't trigger when typing in text inputs/textareas
        const isTextInput = e.target.tagName === 'TEXTAREA' ||
            e.target.isContentEditable ||
            (e.target.tagName === 'INPUT' && !['checkbox', 'radio', 'button', 'submit', 'reset', 'file', 'image'].includes(e.target.type));

        if (isTextInput) {
            return;
        }

        // Only active if file list is present
        const fileTable = document.getElementById('fileTable');
        if (!fileTable) return;

        const isCtrl = e.ctrlKey || e.metaKey;
        const key = e.key.toLowerCase();

        // Ctrl+C (Copy) or Ctrl+X (Cut)
        if (isCtrl && (key === 'c' || key === 'x')) {
            const selectedItems = getSelectedItems();

            // Only intercept if we have items selected
            if (selectedItems.length > 0) {
                e.preventDefault();
                copyToClipboard(null, key === 'x' ? 'cut' : 'copy');
            }
            // Otherwise let native copy happen
        }

        // Ctrl+V (Paste)
        if (isCtrl && key === 'v') {
            e.preventDefault();
            pasteFromClipboard();
        }

        // Delete key - Delete selected
        if (e.key === 'Delete') {
            const selectedItems = getSelectedItems();
            if (selectedItems.length > 0) {
                e.preventDefault();
                deleteSelectedItems();
            }
        }

        // Ctrl+A - Select all
        if (isCtrl && key === 'a') {
            e.preventDefault();
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.checked = !selectAll.checked; // Toggle instead of just set true
                selectAll.dispatchEvent(new Event('change'));
            }
        }
    });
}

function initContextMenu() {
    const contextMenu = document.getElementById('contextMenu');
    if (!contextMenu) return;

    // Add right-click listeners to all file/folder rows
    document.addEventListener('contextmenu', function (e) {
        const row = e.target.closest('.file-row, .directory-row');
        if (row && !row.classList.contains('parent-directory')) {
            e.preventDefault();

            contextMenuItem = row.getAttribute('data-name');
            const isDirectory = row.classList.contains('directory-row');

            // Update paste button visibility using new Clipboard system
            const pasteBtn = document.getElementById('contextPaste');
            if (pasteBtn) {
                // Show paste option if clipboard has items (always show, not just on directories)
                pasteBtn.style.display = Clipboard.hasItems() ? 'flex' : 'none';
            }

            // Position and show context menu with boundary detection
            // First, show the menu to get its dimensions
            contextMenu.classList.add('is-active');

            // Get menu dimensions
            const menuWidth = contextMenu.offsetWidth;
            const menuHeight = contextMenu.offsetHeight;

            // Get viewport dimensions
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            // Calculate position (use clientX/clientY for viewport-relative positioning)
            let left = e.clientX;
            let top = e.clientY;

            // Check right boundary
            if (left + menuWidth > viewportWidth) {
                left = viewportWidth - menuWidth - 5; // 5px margin
            }

            // Check bottom boundary
            if (top + menuHeight > viewportHeight) {
                top = viewportHeight - menuHeight - 5; // 5px margin
            }

            // Ensure menu doesn't go off left or top
            left = Math.max(5, left);
            top = Math.max(5, top);

            contextMenu.style.left = left + 'px';
            contextMenu.style.top = top + 'px';
        }
    });

    // Close context menu on click outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.context-menu')) {
            contextMenu.classList.remove('is-active');
        }
    });
}

function contextMenuAction(action) {
    const contextMenu = document.getElementById('contextMenu');
    contextMenu.classList.remove('is-active');

    if (!contextMenuItem) return;

    const currentPath = getCurrentPath();
    const itemPath = currentPath ? currentPath + '/' + contextMenuItem : contextMenuItem;

    switch (action) {
        case 'open':
            if (isDirectory(contextMenuItem)) {
                window.location.href = '?p=' + encodeURIComponent(itemPath);
            } else {
                window.location.href = '?action=view&p=' + encodeURIComponent(currentPath) + '&file=' + encodeURIComponent(contextMenuItem);
            }
            break;

        case 'rename':
            renameItem(contextMenuItem);
            break;

        case 'copy':
            // Use new Clipboard system - copy single item or selected items
            const selectedForCopy = getSelectedItems();
            if (selectedForCopy.length > 0 && selectedForCopy.includes(contextMenuItem)) {
                copyToClipboard(selectedForCopy, 'copy');
            } else {
                copyToClipboard([contextMenuItem], 'copy');
            }
            break;

        case 'cut':
            // Use new Clipboard system - cut single item or selected items
            const selectedForCut = getSelectedItems();
            if (selectedForCut.length > 0 && selectedForCut.includes(contextMenuItem)) {
                copyToClipboard(selectedForCut, 'cut');
            } else {
                copyToClipboard([contextMenuItem], 'cut');
            }
            break;

        case 'paste':
            pasteFromClipboard();
            break;

        case 'download':
            // Check if multiple items selected and right-clicked item is one of them
            const selectedForDownload = getSelectedItems();
            if (selectedForDownload.length > 1 && selectedForDownload.includes(contextMenuItem)) {
                downloadSelected();
            } else {
                window.location.href = '?action=download&p=' + encodeURIComponent(currentPath) + '&file=' + encodeURIComponent(contextMenuItem);
            }
            break;

        case 'permissions':
            const row = document.querySelector(`[data-name="${escapeHtml(contextMenuItem)}"]`);
            if (row) {
                const perms = row.querySelector('td:nth-child(6)')?.textContent.trim();
                changePermissions(contextMenuItem, perms);
            }
            break;

        case 'delete':
            // Check if multiple items selected and right-clicked item is one of them
            const selectedForDelete = getSelectedItems();
            if (selectedForDelete.length > 1 && selectedForDelete.includes(contextMenuItem)) {
                deleteSelectedItems();
            } else {
                deleteItem(contextMenuItem);
            }
            break;
    }
}

function isDirectory(itemName) {
    const row = document.querySelector(`[data-name="${escapeHtml(itemName)}"]`);
    return row && row.classList.contains('directory-row');
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    initDragAndDrop();
    initContextMenu();
    initKeyboardShortcuts();
    Clipboard.updateUI(); // Update paste button visibility on page load
});

// Toolbar Paste Function (for toolbar button)
function performToolbarPaste() {
    pasteFromClipboard();
}
