<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: config.php
 *
 * Last Modified: Tue, 24 Feb 2026 - 11:11:32 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * File Manager Configuration
 * PHP 8.3+ Required
 */

return [
    // Authentication
    'auth' => [
        'require_login' => true, // Set false to bypass login (useful for external app integration)
        'default_user' => 'system', // Username used when require_login is false
        'users' => [
            // Format: 'username' => password_hash('password', PASSWORD_ARGON2ID)
            // Default: admin/admin@123
            'admin' => '$argon2id$v=19$m=65536,t=4,p=1$eUJ3MnNBeU1YTjhmZzhqQQ$SAU4PDqTM/S+WQJkW4iPD3vCDVgjld9wXmS2GgSaD/4',
        ],
        'session_name' => 'fm_session',
        'remember_me' => true, // Enable "Remember Me" checkbox on login
        'remember_duration' => 1800, // Remember Me cookie duration (30 minutes in seconds)
    ],

    // File Manager Settings
    'fm' => [
        // Dynamic folder mode: when true, folder ID comes from URL (e.g., /file-manager/120)
        // The actual path becomes: base_path + folder_id
        'dynamic_folder' => false,

        // Base path for dynamic folder mode (folder ID is appended to this)
        // Example: '/var/www/html/uploads/tasks' + '/120' = '/var/www/html/uploads/tasks/120'
        'base_path' => '/var/www/html/uploads',

        // Parameter name for folder ID (supports both path segment and query param)
        // URL: /file-manager/120 OR /file-manager?folder=120
        'folder_param' => 'folder',

        // Default folder when no ID provided (null = show error, '' = use base_path as-is)
        'default_folder' => null,

        // Root path for static mode (when dynamic_folder = false)
        // This is the original behavior - point directly to a folder
        'root_path' => __DIR__,

        'root_url' => '', // Base URL for assets (optional, for CDN/external hosting)

        // Assets path: URL path where package assets are accessible
        // Default '' = assets are in same directory as entry point (standalone mode)
        // CI4 example: '/filemanager' if you copied assets to public/filemanager/
        // Laravel example: '/vendor/filemanager/assets'
        'assets_path' => '',

        // Base URL: The URL path for file manager actions and redirects
        // Default 'index.php' = standalone mode
        // CI4 example: '/file-manager' or '/file-manager/123' for dynamic folder
        // This is used for form actions and redirect URLs
        'base_url' => 'index.php',
        'title' => 'File Manager', // Application title shown in browser and header
        'language' => 'en', // Language code for HTML lang attribute
        'show_hidden' => true, // Show hidden files (files starting with .)
        'datetime_format' => 'Y-m-d H:i:s', // PHP date format for file timestamps

        // Table column visibility — set any to false to hide that column
        // Note: Name, checkbox, and Actions columns are always visible
        'columns' => [
            'size' => true,  // File/folder size
            'owner' => true,  // File owner (Unix user)
            'modified' => true,  // Last modified date/time
            'permissions' => true,  // Unix permission string (e.g. rwxr-xr-x)
        ],
    ],

    // Upload Settings
    'upload' => [
        'max_file_size' => 50 * 1024 * 1024, // 50MB in bytes
        'allowed_extensions' => ['*'], // ['jpg', 'png', 'pdf'] or ['*'] for all
        'chunk_size' => 1024 * 1024, // Chunk size for Dropzone.js chunked uploads (1MB)
    ],

    // Security
    'security' => [
        'csrf_protection' => true, // Enable CSRF token validation (disable for API integrations)
        'max_login_attempts' => 3, // Maximum failed login attempts before cooldown
        'login_cooldown' => 300, // Lockout duration in seconds (5 minutes)
    ],

    // Permissions (Role-Based Access Control)
    'permissions' => [
        'default_role' => 'admin', // Role applied when no specific role is set
        'roles' => [
            // 'admin' has access to all actions (wildcard)
            'admin' => ['*'],
            // 'editor' can do everything except change file permissions
            'editor' => ['upload', 'download', 'delete', 'rename', 'new_folder', 'copy', 'move', 'view', 'view_pdf', 'extract', 'zip'],
            // 'viewer' can only view and download files
            'viewer' => ['view', 'view_pdf', 'download'],
        ],
    ],

    // System
    'system' => [
        'timezone' => 'UTC',
        'error_reporting' => E_ALL,
        'display_errors' => false,
        'log_errors' => true,
        'charset' => 'UTF-8',
    ],

    // Excluded items (won't be shown in file manager)
    'exclude_items' => [
        '.git',
        '.gitignore',
        '.htaccess',
        'config.php',
        'vendor',
        'node_modules',
    ],
];
