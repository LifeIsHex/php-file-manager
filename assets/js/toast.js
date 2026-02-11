/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: toast.js
 *
 * Last Modified: Tue, 10 Feb 2026 - 17:43:20 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

// Toast Notification System
function showToast(message, type = 'info', duration = 4000) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    // Add icon based on type
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    toast.innerHTML = `
        <i class="fas ${icons[type] || icons.info} toast-icon"></i>
        <span>${escapeHtml(message)}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;

    document.body.appendChild(toast);

    // Auto-remove after duration
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Show flash message if exists (called on page load)
function showFlashMessage() {
    const flashData = document.getElementById('flash-message-data');
    if (flashData) {
        const type = flashData.getAttribute('data-type');
        const text = flashData.getAttribute('data-text');
        if (type && text) {
            showToast(text, type);
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', showFlashMessage);
