<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: index.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:31:37 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * PHP File Manager
 * Modern PHP 8.3 Implementation with Bulma CSS
 *
 * This is the standalone entry point. For integration with other apps,
 * use FileManagerService instead.
 *
 * @see \FileManager\Integration\FileManagerService
 * @author File Manager Team
 * @license MIT
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Require Composer autoloader
require __DIR__ . '/vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/config.php';

// Set timezone
date_default_timezone_set($config['system']['timezone']);

// Set error reporting from config
error_reporting($config['system']['error_reporting']);
ini_set('display_errors', $config['system']['display_errors'] ? '1' : '0');
ini_set('log_errors', $config['system']['log_errors'] ? '1' : '0');

// Set charset
ini_set('default_charset', $config['system']['charset']);

// Import classes
use FileManager\Auth\AuthManager;
use FileManager\FileManager\DirectoryManager;
use FileManager\FileManager\FileOperations;
use FileManager\Http\Request;
use FileManager\Http\Router;
use FileManager\Security\CsrfProtection;
use FileManager\Security\PermissionManager;
use FileManager\Security\Validator;
use FileManager\Utilities\MessageManager;
use FileManager\Utilities\SessionManager;

try {
    // Create request instance
    $request = new Request();

    // Resolve root path based on configuration mode
    $rootPath = resolveRootPath($config, $request);

    // Update config with resolved path
    $config['fm']['root_path'] = $rootPath;

    // Initialize dependencies
    $csrf = new CsrfProtection();
    $messages = new MessageManager();
    $auth = new AuthManager($config, $csrf);

    // Initialize file manager components with resolved path
    $dirManager = new DirectoryManager(
        $rootPath,
        $config['exclude_items'],
        $config
    );

    $fileOps = new FileOperations(
        $rootPath,
        $config
    );

    // Create session manager
    $session = new SessionManager();

    // Create permission manager
    $permissions = new PermissionManager($config);

    // Initialize router and handle request
    $router = new Router(
        $request,
        $auth,
        $dirManager,
        $fileOps,
        $messages,
        $csrf,
        $session,
        $permissions,
        $config
    );

    $router->handle();

} catch (Throwable $e) {
    // Log error
    error_log('File Manager Error: ' . $e->getMessage());

    // Resolve assets path - allows customization when integrated into other apps
    $assetsPath = rtrim($config['fm']['assets_path'] ?? '', '/');
    if ($assetsPath === '') {
        $assetsPath = 'assets'; // Default: relative to entry point
    }

    // Show user-friendly error
    http_response_code(is_a($e, \RuntimeException::class) ? 400 : 500);
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - Error</title>
    <link rel="stylesheet" href="' . htmlspecialchars($assetsPath) . '/bulma/css/bulma.min.css">
</head>
<body>
<section class="hero is-danger is-fullheight">
    <div class="hero-body">
        <div class="container has-text-centered">
            <h1 class="title">Error</h1>
            <p class="subtitle">';

    if ($config['system']['display_errors']) {
        echo htmlspecialchars($e->getMessage());
    } else {
        echo 'An error occurred. Please contact the administrator.';
    }

    echo '</p>
            <a href="javascript:history.back()" class="button is-light">Go Back</a>
        </div>
    </div>
</section>
</body>
</html>';
}

/**
 * Resolve the root path based on configuration mode
 *
 * @param array $config Configuration array
 * @param Request $request Request instance
 * @return string Resolved absolute path
 * @throws RuntimeException If path cannot be resolved
 */
function resolveRootPath(array $config, Request $request): string
{
    $fmConfig = $config['fm'];

    // Static mode: use root_path directly (original behavior)
    if (!($fmConfig['dynamic_folder'] ?? false)) {
        $rootPath = $fmConfig['root_path'] ?? __DIR__;

        if (!is_dir($rootPath)) {
            throw new RuntimeException("Root path does not exist: $rootPath");
        }

        return realpath($rootPath);
    }

    // Dynamic folder mode: resolve from URL
    $basePath = $fmConfig['base_path'] ?? '';

    if (empty($basePath)) {
        throw new RuntimeException('Dynamic folder mode requires fm.base_path to be set in config.');
    }

    // Get folder ID from URL or query param
    $paramName = $fmConfig['folder_param'] ?? 'folder';
    $folderId = $request->getFolderId($paramName) ?? ($fmConfig['default_folder'] ?? null);

    if ($folderId === null) {
        throw new RuntimeException(
            'No folder specified. Use URL path (e.g., /file-manager/123) or query parameter (?folder=123).'
        );
    }

    // Validate folder ID format (alphanumeric, dashes, underscores only)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $folderId)) {
        throw new RuntimeException("Invalid folder ID format: only alphanumeric, dashes, and underscores allowed.");
    }

    // Build full path
    $resolvedPath = rtrim($basePath, '/') . '/' . $folderId;

    // Verify base path exists
    $realBasePath = realpath($basePath);
    if ($realBasePath === false) {
        throw new RuntimeException("Base path does not exist: $basePath");
    }

    // Create folder if it doesn't exist
    if (!is_dir($resolvedPath)) {
        if (!mkdir($resolvedPath, 0755, true) && !is_dir($resolvedPath)) {
            throw new RuntimeException("Failed to create folder: $resolvedPath");
        }
    }

    $realResolvedPath = realpath($resolvedPath);

    // Security check: prevent path traversal
    if (!str_starts_with($realResolvedPath, $realBasePath)) {
        throw new RuntimeException("Security error: Invalid path detected.");
    }

    return $realResolvedPath;
}
