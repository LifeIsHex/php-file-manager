<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: integration.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:21:22 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * Example: File Manager Integration Entry Point
 *
 * This file demonstrates how to integrate the file manager with your
 * PHP application (CodeIgniter, Laravel, custom framework, etc.)
 *
 * Place this file where it's accessible via your web server and
 * configure your routes accordingly.
 *
 * Example URLs:
 * - /file-manager/120          → Manages folder '120'
 * - /file-manager?folder=120   → Same, using query param
 * - /file-manager/task-abc     → Folder IDs can be alphanumeric
 */

// Require Composer autoloader (adjust path as needed)
require __DIR__ . '/vendor/autoload.php';

use FileManager\Integration\FileManagerService;

// Configuration for the file manager
// In a real app, you might load this from your app's config system
$config = [
    'auth' => [
        // Disable login - your app handles authentication
        'require_login' => false,
        'default_user' => 'app_user', // Shown in UI when login disabled
    ],

    'fm' => [
        // Enable dynamic folder mode
        'dynamic_folder' => true,

        // Base path where all managed folders are stored
        // Folder ID from URL is appended to this
        'base_path' => '/var/www/html/uploads/tasks',

        // Query parameter name (also supports path segments)
        'folder_param' => 'folder',

        // What to do when no folder is specified
        // null = show error, '' = allow base path, 'default' = use this folder
        'default_folder' => null,

        // Assets path: URL path to package assets (CSS, JS)
        // Set this when integrating into CI4/Laravel/etc.
        // Example: '/filemanager' if assets are in public/filemanager/
        'assets_path' => '/filemanager',

        // Base URL: URL path for redirects and form actions
        // Set this to match your framework's route
        // Example: '/file-manager' or '/file-manager/123' for dynamic folder
        'base_url' => '/file-manager',

        // UI customization
        'title' => 'Project Files',
        'show_hidden' => false,
    ],

    'upload' => [
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'allowed_extensions' => ['jpg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'],
    ],

    'security' => [
        // Disable CSRF if your app handles it
        'csrf_protection' => false,
    ],

    'system' => [
        'display_errors' => false,
        'log_errors' => true,
    ],

    'exclude_items' => [
        '.git',
        '.htaccess',
    ],
];

// OPTION 1: Let file manager resolve folder from URL automatically
$fm = new FileManagerService($config);
$fm->render();

// OPTION 2: Set folder programmatically (e.g., from your app's route)
// $fm = new FileManagerService($config);
// $fm->setFolder($taskId)->render();

// OPTION 3: Add permission checking
// $fm = new FileManagerService($config);
// $fm->setAccessValidator(function(string $folderId) use ($currentUser) {
//     // Check if current user can access this folder
//     return $this->taskModel->userCanAccess($currentUser->id, $folderId);
// });
// $fm->render();
