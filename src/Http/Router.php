<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: Router.php
 *
 * Last Modified: Thu, 26 Feb 2026 - 21:25:10 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Http;

use FileManager\Auth\AuthManager;
use FileManager\FileManager\DirectoryManager;
use FileManager\FileManager\FileOperations;
use FileManager\Security\CsrfProtection;
use FileManager\Security\PermissionManager;
use FileManager\Security\Validator;
use FileManager\Utilities\MessageManager;
use FileManager\Utilities\SessionManager;

/**
 * Simple Router
 * Routes actions to appropriate handlers
 */
class Router
{
    public function __construct(
        private readonly Request           $request,
        private readonly AuthManager       $auth,
        private readonly ?DirectoryManager $dirManager,
        private readonly ?FileOperations   $fileOps,
        private readonly MessageManager    $messages,
        private readonly CsrfProtection    $csrf,
        private readonly SessionManager    $session,
        private readonly PermissionManager $permissions,
        private readonly array             $config,
    )
    {
    }

    /**
     * Get the base URL for redirects and form actions
     * Uses config value or defaults to 'index.php' for standalone mode
     */
    private function getBaseUrl(): string
    {
        return $this->config['fm']['base_url'] ?? 'index.php';
    }

    /**
     * Handle incoming request
     */
    public function handle(): void
    {
        // Check authentication
        if (!$this->auth->isAuthenticated()) {
            $this->handleLogin();
            return;
        }

        // Handle logout
        if ($this->request->get('action') === 'logout') {
            $this->auth->logout();
            Response::redirect($this->getBaseUrl());
        }

        // Get action from request
        $action = $this->request->get('action', $this->request->post('action', 'index'));

        // Map actions to permission names
        $permissionMap = [
            'upload' => 'upload',
            'download' => 'download',
            'download-multiple' => 'download',
            'delete' => 'delete',
            'delete-multiple' => 'delete',
            'rename' => 'rename',
            'new' => 'new_folder',
            'copy' => 'copy',
            'move' => 'move',
            'paste' => 'move',  // paste is part of copy/move
            'select-destination' => 'move',
            'execute-copy-move' => 'move',
            'chmod' => 'permissions',
            'view' => 'view',
            'view-pdf' => 'view_pdf',
            'save' => 'rename',  // save edits requires rename-level access
            'zip' => 'zip',
            'extract' => 'extract',
        ];

        // Check permission for the action
        $requiredPermission = $permissionMap[$action] ?? null;
        if ($requiredPermission && !$this->permissions->can($requiredPermission)) {
            $this->messages->error('You do not have permission to perform this action.');
            Response::redirect($this->getBaseUrl() . '?p=' . urlencode($this->request->get('p', '')));
            return;
        }

        // Route to appropriate handler
        match ($action) {
            'upload' => $this->handleUpload(),
            'download' => $this->handleDownload(),
            'download-multiple' => $this->handleDownloadMultiple(),
            'delete' => $this->handleDelete(),
            'delete-multiple' => $this->handleDeleteMultiple(),
            'rename' => $this->handleRename(),
            'new' => $this->handleNewDirectory(),
            'copy' => $this->handleCopy(),
            'move' => $this->handleMove(),
            'paste' => $this->handlePaste(),
            'select-destination' => $this->handleSelectDestination(),
            'execute-copy-move' => $this->handleExecuteCopyMove(),
            'folder-tree' => $this->handleFolderTree(),
            'search' => $this->handleSearch(),
            'chmod' => $this->handleChmod(),
            'view' => $this->handleView(),
            'view-pdf' => $this->handleViewPdf(),
            'save' => $this->handleSave(),
            'zip' => $this->handleZip(),
            'extract' => $this->handleExtract(),
            default => $this->handleIndex(),
        };
    }

    /**
     * Handle login
     */
    private function handleLogin(): void
    {
        if ($this->request->isPost()) {
            $username = $this->request->post('username', '');
            $password = $this->request->post('password', '');
            $token = $this->request->post('csrf_token', '');
            $rememberMe = $this->request->post('remember_me', '') === 'on';

            // Validate CSRF token if protection is enabled
            $csrfValid = true;
            if ($this->config['security']['csrf_protection'] ?? true) {
                $csrfValid = $this->csrf->validateToken($token);
                if (!$csrfValid) {
                    $this->messages->error('Invalid security token');
                }
            }

            if ($csrfValid && $this->auth->login($username, $password, $rememberMe)) {
                Response::redirect($this->getBaseUrl());
            } else {
                $cooldown = $this->auth->getRemainingCooldown();
                if ($cooldown > 0) {
                    $this->messages->error("Too many failed attempts. Try again in $cooldown seconds.");
                } else {
                    $this->messages->error('Invalid username or password');
                }
            }
        }

        // Make variables available in template scope
        $config = $this->config;
        $csrf = $this->csrf;
        $messages = $this->messages;

        require __DIR__ . '/../../templates/auth/login.php';
    }

    /**
     * Handle main file listing
     */
    private function handleIndex(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));

        // Get directory contents
        $contents = $this->dirManager->listContents($currentPath);

        // Get statistics
        $statistics = $this->dirManager->getStatistics($currentPath);

        // Get breadcrumbs
        $breadcrumbs = $this->dirManager->getBreadcrumbs($currentPath);

        // Make config available to template
        $config = $this->config;

        // Make permissions available to template
        $permissions = $this->permissions;

        // Get flash message for notifications (e.g., after copy/move operations)
        $flashMessage = $this->session->getFlashMessage();

        require __DIR__ . '/../../templates/filemanager/index.php';
    }

    /**
     * Handle file upload
     */
    private function handleUpload(): void
    {
        $currentPath = Validator::cleanPath($this->request->post('p', ''));
        $files = $_FILES; // Get all uploaded files directly

        // Check if we have any uploaded files
        $hasFiles = false;
        if (isset($files['upload'])) {
            // Dropzone format
            $hasFiles = !empty($files['upload']['name']);
            $uploadFiles = $files['upload'];
        } elseif (isset($files['name'])) {
            // Traditional format
            $hasFiles = !empty($files['name'][0]);
            $uploadFiles = $files;
        } else {
            $uploadFiles = [];
        }

        if ($hasFiles) {
            $result = $this->fileOps->upload($uploadFiles, $currentPath);

            // Check if this is an AJAX request (Dropzone)
            if (
                !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            ) {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            }

            // Traditional form submission
            if ($result['success']) {
                $this->messages->success($result['message']);
            } else {
                $this->messages->error($result['message']);
            }

            foreach ($result['errors'] as $error) {
                $this->messages->warning($error);
            }
        }

        Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
    }

    /**
     * Handle file download
     */
    private function handleDownload(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));
        $filename = $this->request->get('file', '');

        $this->fileOps->download($currentPath, $filename);
        Response::error('File not found', 404);
    }

    /**
     * Handle multiple file download (creates zip)
     * Expects POST request with JSON body: { items: ['file1', 'file2'], path: 'current/path' }
     */
    private function handleDownloadMultiple(): void
    {
        if (!$this->request->isPost()) {
            Response::json(['success' => false, 'message' => 'POST required']);
            return;
        }

        // Get JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['items']) || !is_array($input['items'])) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $items = $input['items'];
        $path = Validator::cleanPath($input['path'] ?? '');

        if (empty($items)) {
            Response::json(['success' => false, 'message' => 'No items specified']);
            return;
        }

        // For single file, redirect to normal download
        if (count($items) === 1) {
            $singleFile = $items[0];
            $this->fileOps->download($path, $singleFile);
            Response::json(['success' => false, 'message' => 'File not found']);
            return;
        }

        // Multiple files - create zip
        // Use the downloadZip method from FileOperations
        if (!$this->fileOps->downloadZip($path, $items, 'files_' . date('Y-m-d_His') . '.zip')) {
            Response::json(['success' => false, 'message' => 'Failed to create zip file']);
        }
    }

    /**
     * Recursively add directory to zip
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $dirPath, string $zipPath): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            $relativePath = $zipPath . '/' . substr($filePath, strlen($dirPath) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    /**
     * Handle file/directory deletion
     */
    private function handleDelete(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));
        $name = $this->request->get('name', '');

        if ($this->fileOps->delete($currentPath, $name)) {
            $this->session->setFlashMessage('success', "Deleted: $name");
        } else {
            $this->session->setFlashMessage('error', "Failed to delete: $name");
        }

        Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
    }

    /**
     * Handle multiple file/directory deletion (AJAX)
     * Expects POST request with JSON body: { items: ['file1', 'file2'], path: 'current/path' }
     */
    private function handleDeleteMultiple(): void
    {
        if (!$this->request->isPost()) {
            Response::json(['success' => false, 'message' => 'POST required']);
            return;
        }

        // Get JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['items']) || !is_array($input['items'])) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $items = $input['items'];
        $path = Validator::cleanPath($input['path'] ?? '');

        if (empty($items)) {
            Response::json(['success' => false, 'message' => 'No items specified']);
            return;
        }

        $result = $this->fileOps->deleteMultiple($items, $path);

        Response::json($result);
    }

    /**
     * Handle paste operation (AJAX)
     * Expects POST request with JSON body: { items: ['file1'], sourcePath: '', destPath: '', operation: 'copy'|'cut' }
     */
    private function handlePaste(): void
    {
        if (!$this->request->isPost()) {
            Response::json(['success' => false, 'message' => 'POST required']);
            return;
        }

        // Get JSON body
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['items']) || !is_array($input['items'])) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $items = $input['items'];
        $sourcePath = Validator::cleanPath($input['sourcePath'] ?? '');
        $destPath = Validator::cleanPath($input['destPath'] ?? '');
        $operation = $input['operation'] ?? 'copy';

        if (empty($items)) {
            Response::json(['success' => false, 'message' => 'No items to paste']);
            return;
        }

        if ($operation === 'cut') {
            $result = $this->fileOps->moveMultiple($items, $sourcePath, $destPath);
        } else {
            $result = $this->fileOps->copyMultiple($items, $sourcePath, $destPath);
        }

        Response::json($result);
    }

    /**
     * Handle rename
     */
    private function handleRename(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));
        $oldName = $this->request->get('old', '');
        $newName = $this->request->get('new', '');

        if ($this->fileOps->rename($currentPath, $oldName, $newName)) {
            $this->session->setFlashMessage('success', "Renamed to: $newName");
        } else {
            $this->session->setFlashMessage('error', "Failed to rename: $oldName");
        }

        Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
    }

    /**
     * Handle new directory creation
     */
    private function handleNewDirectory(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));
        $name = $this->request->get('name', '');

        if ($this->dirManager->createDirectory($currentPath, $name)) {
            $this->session->setFlashMessage('success', "Created directory: $name");
        } else {
            $this->session->setFlashMessage('error', "Failed to create directory: $name");
        }

        Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
    }

    /**
     * Handle copy operation - store in session and redirect to destination browser
     */
    private function handleCopy(): void
    {
        $file = $this->request->get('file', '');
        $currentPath = Validator::cleanPath($this->request->get('p', ''));

        if (empty($file)) {
            $this->messages->error('No file specified for copy');
            Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
            return;
        }

        // Store operation in session
        $this->session->setPendingOperation([
            'operation' => 'copy',
            'source' => $file,
            'sourcePath' => $currentPath,
        ]);

        // Redirect to destination browser starting from current path
        Response::redirect($this->getBaseUrl() . '?action=select-destination&p=' . urlencode($currentPath));
    }

    /**
     * Handle move operation - store in session and redirect to destination browser
     */
    private function handleMove(): void
    {
        $file = $this->request->get('file', '');
        $currentPath = Validator::cleanPath($this->request->get('p', ''));

        if (empty($file)) {
            $this->messages->error('No file specified for move');
            Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
            return;
        }

        // Store operation in session
        $this->session->setPendingOperation([
            'operation' => 'move',
            'source' => $file,
            'sourcePath' => $currentPath,
        ]);

        // Redirect to destination browser starting from current path
        Response::redirect($this->getBaseUrl() . '?action=select-destination&p=' . urlencode($currentPath));
    }

    /**
     * Handle folder tree (AJAX) - Return directory structure as JSON
     */
    private function handleFolderTree(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));
        $rootPath = $this->dirManager->getRootPath();
        $fullPath = $rootPath . ($currentPath ? DIRECTORY_SEPARATOR . $currentPath : '');

        $folders = [];

        if (is_dir($fullPath) && Validator::isWithinRoot($fullPath, $rootPath)) {
            $items = scandir($fullPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
                if (is_dir($itemPath)) {
                    $relativePath = $currentPath ? $currentPath . '/' . $item : $item;
                    $folders[] = [
                        'name' => $item,
                        'path' => $relativePath
                    ];
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'folders' => $folders]);
        exit;
    }

    /**
     * Handle search (AJAX)
     */
    private function handleSearch(): void
    {
        $query = $this->request->get('q', '');
        $currentPath = Validator::cleanPath($this->request->get('p', ''));

        $results = $this->dirManager->search($query, $currentPath);

        // Strip fields for columns that are configured as hidden,
        // so sensitive data is never sent to the client.
        // NOTE: `permissions` is intentionally NOT stripped even when its column is hidden,
        // because the chmod modal needs the current value regardless of column visibility.
        // Use the PermissionManager `chmod` rule to actually restrict access to the chmod operation.
        $cols = $this->config['fm']['columns'] ?? [];
        $showSize = $cols['size'] ?? true;
        $showOwner = $cols['owner'] ?? true;
        $showModified = $cols['modified'] ?? true;

        $hiddenFields = [];
        if (!$showSize) {
            $hiddenFields[] = 'size';
            $hiddenFields[] = 'size_formatted';
        }
        if (!$showOwner) {
            $hiddenFields[] = 'owner';
        }
        if (!$showModified) {
            $hiddenFields[] = 'modified';
        }

        if (!empty($hiddenFields)) {
            $strip = static function (array $items) use ($hiddenFields): array {
                return array_map(static function (array $item) use ($hiddenFields): array {
                    foreach ($hiddenFields as $field) {
                        unset($item[$field]);
                    }
                    return $item;
                }, $items);
            };

            $results['directories'] = $strip($results['directories'] ?? []);
            $results['files'] = $strip($results['files'] ?? []);
        }

        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }

    /**
     * Handle permission change (AJAX)
     */
    private function handleChmod(): void
    {
        $currentPath = Validator::cleanPath($this->request->post('p', ''));
        $name = $this->request->post('name', '');
        $mode = $this->request->post('mode', '');

        $success = $this->fileOps->changePermissions($currentPath, $name, $mode);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Permissions updated' : 'Failed to update permissions'
        ]);
        exit;
    }

    /**
     * Handle file view/preview
     */
    private function handleView(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));
        $filename = $this->request->get('file', '');

        $fileInfo = $this->fileOps->getFileInfo($currentPath, $filename);

        if (!$fileInfo) {
            $this->messages->error('File not found');
            Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
            return;
        }

        $fullPath = $fileInfo['full_path'];
        $mimeType = $fileInfo['mime_type'];
        $isImage = $fileInfo['is_image'];
        $isText = $fileInfo['is_text'];
        $isHeic = $fileInfo['is_heic'] ?? false;

        // Read file content if it's an image or text file
        $fileContent = null;
        $imageInfo = null;

        if ($isImage || $isHeic) {
            if ($isHeic) {
                // Convert HEIC to JPEG for browser display
                $fileContent = $this->fileOps->convertHeicToJpeg($fullPath);
                if ($fileContent) {
                    $mimeType = 'image/jpeg'; // Override for display
                }
            } else {
                $fileContent = $this->fileOps->readFile($currentPath, $filename);
            }

            // Get image dimensions with HEIC support
            $imageInfo = $this->fileOps->getImageDimensions($fullPath);
        } elseif ($isText) {
            $fileContent = $this->fileOps->readFile($currentPath, $filename);
        }

        // Make config available to template
        $config = $this->config;

        require __DIR__ . '/../../templates/filemanager/preview.php';
    }

    /**
     * Handle PDF view inline in browser
     */
    private function handleViewPdf(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));
        $filename = $this->request->get('file', '');

        $fileInfo = $this->fileOps->getFileInfo($currentPath, $filename);

        if (!$fileInfo) {
            Response::error('File not found', 404);
            return;
        }

        $fullPath = $fileInfo['full_path'];

        // Verify it's a PDF file
        if ($fileInfo['mime_type'] !== 'application/pdf') {
            Response::error('Not a PDF file', 400);
            return;
        }

        // Set headers to display PDF inline in browser
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Output the PDF file
        readfile($fullPath);
        exit;
    }

    /**
     * Handle file save
     */
    private function handleSave(): void
    {
        $currentPath = Validator::cleanPath($this->request->post('p', ''));
        $filename = $this->request->post('file', '');
        $content = $this->request->post('content', '');

        if ($this->fileOps->writeFile($currentPath, $filename, $content)) {
            $this->session->setFlashMessage('success', "File saved: $filename");
        } else {
            $this->session->setFlashMessage('error', "Failed to save file: $filename");
        }

        Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
    }

    /**
     * Handle ZIP creation
     */
    private function handleZip(): void
    {
        $currentPath = Validator::cleanPath($this->request->post('p', ''));
        $items = $this->request->post('items', []);
        $zipName = $this->request->post('zipname', 'archive');

        if (empty($items)) {
            $this->session->setFlashMessage('error', 'No items selected for compression');
            Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
            return;
        }

        $result = $this->fileOps->createZip($items, $currentPath, $zipName);

        if ($result) {
            $this->session->setFlashMessage('success', "Created archive: $result");
        } else {
            $this->session->setFlashMessage('error', 'Failed to create archive');
        }

        Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
    }

    /**
     * Handle ZIP extraction
     */
    private function handleExtract(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));
        $zipName = $this->request->get('file', '');
        $targetFolder = $this->request->get('target_folder', null);

        if ($this->fileOps->extractZip($currentPath, $zipName, $targetFolder)) {
            $msg = "Extracted: $zipName";
            if ($targetFolder) {
                $msg .= " to $targetFolder";
            }
            $this->session->setFlashMessage('success', $msg);
        } else {
            $error = $this->fileOps->getLastError();
            if (empty($error)) {
                $error = "Failed to extract: $zipName";
            }
            $this->session->setFlashMessage('error', $error);
        }

        Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
    }

    /**
     * Handle destination browser page
     */
    private function handleSelectDestination(): void
    {
        $currentPath = Validator::cleanPath($this->request->get('p', ''));

        // Get pending operation from session
        $operation = $this->session->getPendingOperation();

        if (!$operation) {
            $this->messages->error('No pending operation found');
            Response::redirect($this->getBaseUrl() . '?p=' . urlencode($currentPath));
            return;
        }

        // Get directory contents (folders only)
        $contents = $this->dirManager->listContents($currentPath);
        $folders = $contents['directories'];

        // Get breadcrumbs
        $breadcrumbs = $this->dirManager->getBreadcrumbs($currentPath);

        // Prepare data for template
        $title = ucfirst($operation['operation']) . ' - Select Destination';
        $username = $this->auth->getUsername();
        $messages = $this->messages;
        $flashMessage = $this->session->getFlashMessage();
        $csrf = $this->csrf;
        $config = $this->config;

        // Render destination browser template
        ob_start();
        require __DIR__ . '/../../templates/filemanager/select_destination.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../templates/layout.php';
    }

    /**
     * Execute copy/move operation with error handling
     */
    private function handleExecuteCopyMove(): void
    {
        // Get pending operation from session
        $operation = $this->session->getPendingOperation();

        if (!$operation) {
            $this->session->setFlashMessage('error', 'No pending operation found');
            Response::redirect($this->getBaseUrl());
            return;
        }

        $destinationPath = Validator::cleanPath($this->request->post('destination', ''));
        $sourcePath = $operation['sourcePath'];
        $sourceFile = $operation['source'];
        $operationType = $operation['operation'];

        // Build full paths
        $fullSource = $sourcePath ? $sourcePath . '/' . $sourceFile : $sourceFile;

        try {
            if ($operationType === 'copy') {
                $result = $this->fileOps->copyItem($fullSource, $destinationPath);
            } else {
                $result = $this->fileOps->moveItem($fullSource, $destinationPath);
            }

            if ($result['success']) {
                $destDisplay = $destinationPath ?: '/';
                $this->session->setFlashMessage(
                    'success',
                    ucfirst($operationType) . "d \"{$sourceFile}\" to {$destDisplay}"
                );
            } else {
                $this->session->setFlashMessage('error', $result['message']);
            }
        } catch (\Exception $e) {
            $this->session->setFlashMessage(
                'error',
                ucfirst($operationType) . " failed: " . $e->getMessage()
            );
        }

        // Clear pending operation
        $this->session->clearPendingOperation();

        // Redirect back to source location
        Response::redirect($this->getBaseUrl() . '?p=' . urlencode($sourcePath));
    }
}

