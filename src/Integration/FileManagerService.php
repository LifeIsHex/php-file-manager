<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: FileManagerService.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:43:16 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Integration;

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

/**
 * FileManagerService
 *
 * Main integration class for embedding the file manager in host applications.
 * Provides a clean API for initializing and rendering the file manager with
 * dynamic folder access and security sandboxing.
 *
 * @example Basic usage in a controller:
 * ```php
 * $fm = new FileManagerService($config);
 * $fm->setFolder('120')->render();
 * ```
 *
 * @example With permission callback:
 * ```php
 * $fm = new FileManagerService($config);
 * $fm->setAccessValidator(fn($folderId) => $this->userCanAccess($folderId));
 * $fm->setFolder($taskId)->render();
 * ```
 */
class FileManagerService
{
    private array $config;
    private ?string $folderId = null;
    private ?string $resolvedRootPath = null;
    private ?\Closure $accessValidator = null;
    private ?Request $request = null;
    private ?string $permissionRole = null;

    /**
     * Create a new FileManagerService instance
     *
     * @param array $config Configuration array (from config.php or custom)
     */
    public function __construct(array $config = [])
    {
        $this->config = $this->mergeWithDefaults($config);
        $this->request = new Request();
    }

    /**
     * Merge provided config with sensible defaults
     */
    private function mergeWithDefaults(array $config): array
    {
        $defaults = [
            'auth' => [
                'require_login' => true,
                'default_user' => 'system',
                'users' => [],
                'session_name' => 'fm_session',
                'remember_me' => true,
                'remember_duration' => 1800,
            ],
            'fm' => [
                'dynamic_folder' => false,
                'base_path' => '',
                'folder_param' => 'folder',
                'default_folder' => null,
                'root_path' => '',
                'root_url' => '',
                'assets_path' => '',
                'base_url' => 'index.php',
                'title' => 'File Manager',
                'language' => 'en',
                'show_hidden' => false,
                'datetime_format' => 'Y-m-d H:i:s',
            ],
            'upload' => [
                'max_file_size' => 50 * 1024 * 1024,
                'allowed_extensions' => ['*'],
                'chunk_size' => 1024 * 1024,
            ],
            'security' => [
                'csrf_protection' => true,
                'max_login_attempts' => 50,
                'login_cooldown' => 300,
            ],
            'system' => [
                'timezone' => 'UTC',
                'error_reporting' => E_ALL,
                'display_errors' => false,
                'log_errors' => true,
                'charset' => 'UTF-8',
            ],
            'exclude_items' => [
                '.git',
                '.gitignore',
                '.htaccess',
                'config.php',
                'vendor',
                'node_modules',
            ],
            'permissions' => [
                'default_role' => 'admin',
                'roles' => [
                    'admin' => ['*'],
                    'editor' => ['upload', 'download', 'delete', 'rename', 'new_folder',
                        'copy', 'move', 'view', 'view_pdf', 'extract', 'zip'],
                    'viewer' => ['view', 'view_pdf', 'download'],
                ],
            ],
        ];

        return array_replace_recursive($defaults, $config);
    }

    /**
     * Set the folder ID to manage
     *
     * @param string $folderId The folder identifier (e.g., '120', 'task-abc')
     * @return self For method chaining
     * @throws \InvalidArgumentException If folder ID contains invalid characters
     */
    public function setFolder(string $folderId): self
    {
        if (!$this->validateFolderId($folderId)) {
            throw new \InvalidArgumentException(
                "Invalid folder ID: '$folderId'. Only alphanumeric characters, dashes, and underscores are allowed."
            );
        }

        $this->folderId = $folderId;
        $this->resolvedRootPath = null; // Reset cache
        return $this;
    }

    /**
     * Get the current folder ID
     */
    public function getFolder(): ?string
    {
        return $this->folderId;
    }

    /**
     * Set a custom access validator callback
     *
     * The callback receives the folder ID and should return true if access is allowed.
     *
     * @param \Closure $validator Callback function: fn(string $folderId): bool
     * @return self For method chaining
     */
    public function setAccessValidator(\Closure $validator): self
    {
        $this->accessValidator = $validator;
        return $this;
    }

    /**
     * Set the permission role for the current user
     *
     * This overrides the default_role from config.
     * The role must exist in config permissions.roles.
     *
     * @param string $role Role name (e.g., 'admin', 'editor', 'viewer')
     * @return self For method chaining
     */
    public function setPermissionRole(string $role): self
    {
        $this->permissionRole = $role;
        return $this;
    }

    /**
     * Validate folder ID format
     * Only allows alphanumeric characters, dashes, underscores
     */
    private function validateFolderId(string $folderId): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_-]+$/', $folderId);
    }

    /**
     * Check if access to the folder is allowed
     *
     * @param string $folderId The folder to check
     * @return bool True if access is allowed
     */
    public function validateAccess(string $folderId): bool
    {
        // If custom validator is set, use it
        if ($this->accessValidator !== null) {
            return ($this->accessValidator)($folderId);
        }

        // Default: allow all valid folder IDs
        return $this->validateFolderId($folderId);
    }

    /**
     * Resolve the folder ID from URL (path segment or query param)
     *
     * Supports:
     * - Path: /file-manager/120
     * - Query: /file-manager?folder=120
     *
     * @return string|null The folder ID, or null if not found
     */
    public function resolveFolderFromUrl(): ?string
    {
        $paramName = $this->config['fm']['folder_param'] ?? 'folder';

        // First, try to get from query parameter
        $folderId = $this->request->get($paramName);

        if ($folderId !== null && $folderId !== '') {
            return (string)$folderId;
        }

        // Try to extract from path segments
        $pathInfo = $_SERVER['PATH_INFO'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Parse PATH_INFO first (cleaner)
        if (!empty($pathInfo)) {
            $segments = array_filter(explode('/', trim($pathInfo, '/')));
            if (!empty($segments)) {
                return array_shift($segments);
            }
        }

        // Fallback: parse REQUEST_URI
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '';
        $segments = array_filter(explode('/', trim($path, '/')));

        // Skip known segments (e.g., 'file-manager', 'index.php')
        $skipSegments = ['file-manager', 'index.php', 'filemanager'];
        foreach ($segments as $segment) {
            if (!in_array(strtolower($segment), $skipSegments, true)) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * Get the resolved root path for file operations
     *
     * In dynamic mode: base_path + folder_id
     * In static mode: root_path as-is
     *
     * @return string The absolute path to manage
     * @throws \RuntimeException If path cannot be resolved or doesn't exist
     */
    public function getRootPath(): string
    {
        // Return cached value if available
        if ($this->resolvedRootPath !== null) {
            return $this->resolvedRootPath;
        }

        $fmConfig = $this->config['fm'];

        // Static mode: use root_path directly
        if (!($fmConfig['dynamic_folder'] ?? false)) {
            $rootPath = $fmConfig['root_path'] ?? '';

            if (empty($rootPath)) {
                throw new \RuntimeException('Static mode requires fm.root_path to be set.');
            }

            if (!is_dir($rootPath)) {
                throw new \RuntimeException("Root path does not exist: $rootPath");
            }

            $this->resolvedRootPath = realpath($rootPath);
            return $this->resolvedRootPath;
        }

        // Dynamic mode: resolve folder
        $basePath = $fmConfig['base_path'] ?? '';

        if (empty($basePath)) {
            throw new \RuntimeException('Dynamic folder mode requires fm.base_path to be set.');
        }

        // Get folder ID (from URL or manually set)
        $folderId = $this->folderId ?? $this->resolveFolderFromUrl() ?? $fmConfig['default_folder'];

        if ($folderId === null) {
            throw new \RuntimeException(
                'No folder ID provided. Use setFolder() or pass folder in URL.'
            );
        }

        // Validate folder ID format
        if (!$this->validateFolderId($folderId)) {
            throw new \RuntimeException("Invalid folder ID format: $folderId");
        }

        // Check access permission
        if (!$this->validateAccess($folderId)) {
            throw new \RuntimeException("Access denied to folder: $folderId");
        }

        // Resolve full path
        $resolvedPath = rtrim($basePath, '/') . '/' . $folderId;

        // Security check: ensure resolved path is under base path
        $realBasePath = realpath($basePath);
        $realResolvedPath = realpath($resolvedPath);

        if ($realBasePath === false) {
            throw new \RuntimeException("Base path does not exist: $basePath");
        }

        // Create folder if it doesn't exist
        if ($realResolvedPath === false) {
            if (!mkdir($resolvedPath, 0755, true) && !is_dir($resolvedPath)) {
                throw new \RuntimeException("Failed to create folder: $resolvedPath");
            }
            $realResolvedPath = realpath($resolvedPath);
        }

        // Verify path is within base path (prevent path traversal)
        if (!str_starts_with($realResolvedPath, $realBasePath)) {
            throw new \RuntimeException("Security error: Path traversal detected");
        }

        $this->resolvedRootPath = $realResolvedPath;
        $this->folderId = $folderId;

        return $this->resolvedRootPath;
    }

    /**
     * Get the configuration with resolved root path
     */
    public function getConfig(): array
    {
        $config = $this->config;
        $config['fm']['root_path'] = $this->getRootPath();
        return $config;
    }

    /**
     * Render the file manager
     *
     * This method handles the full request cycle:
     * - Resolves the root path
     * - Initializes all components
     * - Handles authentication
     * - Routes the request
     * - Outputs the response
     */
    public function render(): void
    {
        try {
            // Resolve and update config with actual root path
            $config = $this->getConfig();

            // Apply system settings
            date_default_timezone_set($config['system']['timezone']);
            error_reporting($config['system']['error_reporting']);
            ini_set('display_errors', $config['system']['display_errors'] ? '1' : '0');
            ini_set('log_errors', $config['system']['log_errors'] ? '1' : '0');
            ini_set('default_charset', $config['system']['charset']);

            // Initialize dependencies
            $csrf = new CsrfProtection();
            $messages = new MessageManager();
            $auth = new AuthManager($config, $csrf);

            $dirManager = new DirectoryManager(
                $config['fm']['root_path'],
                $config['exclude_items'],
                $config
            );

            $fileOps = new FileOperations(
                $config['fm']['root_path'],
                $config
            );

            $session = new SessionManager();

            // Create permission manager
            $permissions = new PermissionManager($config);

            // Apply role override if set via setPermissionRole()
            if ($this->permissionRole !== null) {
                $permissions->setRole($this->permissionRole);
            }

            // Initialize router and handle request
            $router = new Router(
                $this->request,
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

        } catch (\RuntimeException $e) {
            $this->renderError($e->getMessage());
        } catch (\Throwable $e) {
            error_log('File Manager Error: ' . $e->getMessage());
            $this->renderError(
                $this->config['system']['display_errors']
                    ? $e->getMessage()
                    : 'An error occurred. Please contact the administrator.'
            );
        }
    }

    /**
     * Render an error page
     */
    private function renderError(string $message): void
    {
        http_response_code(400);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - Error</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
<section class="hero is-danger is-fullheight">
    <div class="hero-body">
        <div class="container has-text-centered">
            <h1 class="title">Error</h1>
            <p class="subtitle">' . htmlspecialchars($message) . '</p>
            <a href="javascript:history.back()" class="button is-light">Go Back</a>
        </div>
    </div>
</section>
</body>
</html>';
    }

    /**
     * Static helper to create and configure a service instance
     *
     * @param array $config Configuration array
     * @param string|null $folderId Optional folder ID to set immediately
     * @return self Configured service instance
     */
    public static function create(array $config, ?string $folderId = null): self
    {
        $service = new self($config);

        if ($folderId !== null) {
            $service->setFolder($folderId);
        }

        return $service;
    }
}
