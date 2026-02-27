<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: FileOperations.php
 *
 * Last Modified: Thu, 26 Feb 2026 - 20:34:28 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\FileManager;

use FileManager\Security\Validator;

/**
 * File Operations
 * Handles all file operations: upload, download, delete, rename, copy, move
 */
class FileOperations
{
    public function __construct(
        private readonly string $rootPath,
        private readonly array  $config,
    )
    {
    }

    private string $lastError = '';

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Upload file(s)
     */
    public function upload(array $files, string $destinationPath): array
    {
        $cleanPath = Validator::cleanPath($destinationPath);
        $uploadPath = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');

        if (!is_dir($uploadPath) || !Validator::isWithinRoot($uploadPath, $this->rootPath)) {
            return ['success' => false, 'message' => 'Invalid upload path'];
        }

        $uploaded = 0;
        $errors = [];
        $maxSize = $this->config['upload']['max_file_size'] ?? 50 * 1024 * 1024;
        $allowedExtensions = $this->config['upload']['allowed_extensions'] ?? ['*'];

        // Handle Dropzone's uploadMultiple format
        // Dropzone sends files as upload[0], upload[1], etc. when using uploadMultiple
        if (!isset($files['name']) && isset($files['upload'])) {
            $files = $this->reorganizeFilesArray($files['upload']);
        }

        // Handle case where name is not an array (single file)
        if (!is_array($files['name'])) {
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']]
            ];
        }

        foreach ($files['name'] as $index => $filename) {
            if ($files['error'][$index] !== UPLOAD_ERR_OK) {
                $errors[] = "$filename: Upload error";
                continue;
            }

            // Validate file size
            if (!Validator::isValidFileSize($files['size'][$index], $maxSize)) {
                $errors[] = "$filename: File too large";
                continue;
            }

            // Validate extension
            if (!Validator::isAllowedExtension($filename, $allowedExtensions)) {
                $errors[] = "$filename: File type not allowed";
                continue;
            }

            $sanitizedFilename = Validator::sanitizeFilename($filename);
            $targetPath = $uploadPath . DIRECTORY_SEPARATOR . $sanitizedFilename;

            // Handle duplicate names
            if (file_exists($targetPath)) {
                $info = pathinfo($sanitizedFilename);
                $base = $info['filename'];
                $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
                $counter = 1;

                while (file_exists($targetPath)) {
                    $sanitizedFilename = $base . '_' . $counter . $ext;
                    $targetPath = $uploadPath . DIRECTORY_SEPARATOR . $sanitizedFilename;
                    $counter++;
                }
            }

            if (move_uploaded_file($files['tmp_name'][$index], $targetPath)) {
                chmod($targetPath, 0644);
                $uploaded++;
            } else {
                $errors[] = "$filename: Failed to save file";
            }
        }

        return [
            'success' => $uploaded > 0,
            'uploaded' => $uploaded,
            'errors' => $errors,
            'message' => $uploaded > 0 ? "Uploaded $uploaded file(s)" : 'No files uploaded',
        ];
    }

    /**
     * Reorganize files array from Dropzone format
     */
    private function reorganizeFilesArray(array $files): array
    {
        $reorganized = [
            'name' => [],
            'type' => [],
            'tmp_name' => [],
            'error' => [],
            'size' => []
        ];

        foreach ($files as $file) {
            if (is_array($file)) {
                $reorganized['name'][] = $file['name'];
                $reorganized['type'][] = $file['type'];
                $reorganized['tmp_name'][] = $file['tmp_name'];
                $reorganized['error'][] = $file['error'];
                $reorganized['size'][] = $file['size'];
            }
        }

        return $reorganized;
    }

    /**
     * Download file
     */
    public function download(string $relativePath, string $filename): bool
    {
        $filepath = $this->getFullPath($relativePath, $filename);

        if (!$filepath) {
            return false;
        }

        // Validate that path is within root (security)
        if (!Validator::isWithinRoot($filepath, $this->rootPath)) {
            return false;
        }

        // If it's a directory, zip and download it
        if (is_dir($filepath)) {
            $zipName = $filename . '.zip';
            return $this->downloadZip($relativePath, [$filename], $zipName);
        }

        if (!is_file($filepath)) {
            return false;
        }

        // Sanitize filename to prevent header injection
        $safeFilename = $this->sanitizeHeaderFilename($filename);

        // Clear output buffer to prevent pollution
        if (ob_get_level()) ob_end_clean();

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));

        readfile($filepath);
        exit;
    }

    /**
     * Download multiple items as ZIP
     */
    public function downloadZip(string $relativePath, array $items, string $zipName): bool
    {
        $cleanPath = Validator::cleanPath($relativePath);
        $sourcePath = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');

        if (!is_dir($sourcePath) || !Validator::isWithinRoot($sourcePath, $this->rootPath)) {
            return false;
        }

        // Create temporary zip file
        $tempZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('fm_zip_') . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($tempZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $addedCount = 0;
        foreach ($items as $item) {
            // Verify item exists and is within root
            $itemPath = $sourcePath . DIRECTORY_SEPARATOR . $item;
            if (!file_exists($itemPath) || !Validator::isWithinRoot($itemPath, $this->rootPath)) {
                continue;
            }

            if (is_file($itemPath)) {
                $zip->addFile($itemPath, $item);
                $addedCount++;
            } elseif (is_dir($itemPath)) {
                $this->addDirectoryToZip($zip, $itemPath, $item);
                $addedCount++;
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            if (file_exists($tempZip)) unlink($tempZip);
            return false;
        }

        $safeFilename = $this->sanitizeHeaderFilename($zipName);

        // Clear output buffer
        if (ob_get_level()) ob_end_clean();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($tempZip));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        readfile($tempZip);
        unlink($tempZip);
        exit;
    }

    /**
     * Sanitize filename for use in HTTP headers
     * Prevents header injection attacks
     */
    private function sanitizeHeaderFilename(string $filename): string
    {
        // Remove any newlines, carriage returns, and null bytes
        $filename = str_replace(["\r", "\n", "\0"], '', $filename);
        // Remove non-ASCII characters
        $filename = preg_replace('/[^\x20-\x7E]/', '', $filename);
        // Escape quotes
        $filename = str_replace('"', '\\"', $filename);
        return $filename;
    }

    /**
     * Delete file or directory
     */
    public function delete(string $relativePath, string $name): bool
    {
        $filepath = $this->getFullPath($relativePath, $name);

        if (!$filepath) {
            return false;
        }

        if (is_dir($filepath)) {
            return $this->deleteDirectory($filepath);
        }

        return unlink($filepath);
    }

    /**
     * Rename file or directory
     */
    public function rename(string $relativePath, string $oldName, string $newName): bool
    {
        if (!Validator::isValidFileName($newName)) {
            return false;
        }

        $oldPath = $this->getFullPath($relativePath, $oldName);
        if (!$oldPath) {
            return false;
        }

        $cleanPath = Validator::cleanPath($relativePath);
        $parentPath = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');
        $newPath = $parentPath . DIRECTORY_SEPARATOR . $newName;

        if (file_exists($newPath)) {
            return false;
        }

        return rename($oldPath, $newPath);
    }

    /**
     * Copy file or directory
     */
    public function copy(string $sourcePath, string $destPath): bool
    {
        $cleanSource = Validator::cleanPath($sourcePath);
        $cleanDest = Validator::cleanPath($destPath);

        $fullSource = $this->rootPath . DIRECTORY_SEPARATOR . $cleanSource;
        $fullDest = $this->rootPath . DIRECTORY_SEPARATOR . $cleanDest;

        if (
            !Validator::isWithinRoot($fullSource, $this->rootPath) ||
            !Validator::isWithinRoot($fullDest, $this->rootPath)
        ) {
            return false;
        }

        if (!file_exists($fullSource)) {
            return false;
        }

        if (is_dir($fullSource)) {
            // Prevent copying a directory into itself or its own subdirectory
            if ($this->isSubPathOf($fullDest, $fullSource)) {
                return false;
            }
            return $this->copyDirectory($fullSource, $fullDest);
        }

        return copy($fullSource, $fullDest);
    }

    /**
     * Move file or directory
     */
    public function move(string $sourcePath, string $destPath): bool
    {
        // Guard inherited from copy(), but also check explicitly
        $cleanSource = Validator::cleanPath($sourcePath);
        $cleanDest = Validator::cleanPath($destPath);
        $fullSource = $this->rootPath . DIRECTORY_SEPARATOR . $cleanSource;
        $fullDest = $this->rootPath . DIRECTORY_SEPARATOR . $cleanDest;

        if (is_dir($fullSource) && $this->isSubPathOf($fullDest, $fullSource)) {
            return false;
        }

        if ($this->copy($sourcePath, $destPath)) {
            if (is_dir($fullSource)) {
                return $this->deleteDirectory($fullSource);
            }
            return unlink($fullSource);
        }

        return false;
    }

    /**
     * Get full path for a file
     */
    private function getFullPath(string $relativePath, string $filename): ?string
    {
        if (!Validator::isValidFileName($filename)) {
            return null;
        }

        $cleanPath = Validator::cleanPath($relativePath);
        $dirPath = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');
        $fullPath = $dirPath . DIRECTORY_SEPARATOR . $filename;

        if (!Validator::isWithinRoot($fullPath, $this->rootPath) || !file_exists($fullPath)) {
            return null;
        }

        return $fullPath;
    }

    /**
     * Copy directory recursively
     */
    private function copyDirectory(string $source, string $dest): bool
    {
        // Safety net: prevent infinite recursion if dest is inside source
        if ($this->isSubPathOf($dest, $source)) {
            return false;
        }

        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourceItem = $source . DIRECTORY_SEPARATOR . $item;
            $destItem = $dest . DIRECTORY_SEPARATOR . $item;

            if (is_dir($sourceItem)) {
                $this->copyDirectory($sourceItem, $destItem);
            } else {
                copy($sourceItem, $destItem);
            }
        }

        return true;
    }

    /**
     * Check if a path is the same as or a subdirectory of another path.
     * Used to prevent copying/moving a folder into itself.
     */
    private function isSubPathOf(string $child, string $parent): bool
    {
        $realChild = realpath($child);
        $realParent = realpath($parent);

        // If child doesn't exist yet, resolve its parent directory instead
        if ($realChild === false) {
            $realChild = realpath(dirname($child));
            if ($realChild === false) {
                return false;
            }
            $realChild .= DIRECTORY_SEPARATOR . basename($child);
        }

        if ($realParent === false) {
            return false;
        }

        return $realChild === $realParent
            || str_starts_with($realChild . DIRECTORY_SEPARATOR, $realParent . DIRECTORY_SEPARATOR);
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory(string $path): bool
    {
        if (!is_dir($path) || !Validator::isWithinRoot($path, $this->rootPath)) {
            return false;
        }

        $items = scandir($path);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        return rmdir($path);
    }

    /**
     * Change file/directory permissions
     */
    public function changePermissions(string $relativePath, string $name, string $mode): bool
    {
        if (!Validator::isValidFileName($name)) {
            return false;
        }

        $cleanPath = Validator::cleanPath($relativePath);
        $dirPath = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');
        $fullPath = $dirPath . DIRECTORY_SEPARATOR . $name;

        if (!Validator::isWithinRoot($fullPath, $this->rootPath) || !file_exists($fullPath)) {
            return false;
        }

        // Validate permission mode (should be 4-digit octal like 0755)
        if (!preg_match('/^0?[0-7]{3}$/', $mode)) {
            return false;
        }

        // Convert to octal
        $octalMode = octdec($mode);

        return chmod($fullPath, $octalMode);
    }

    /**
     * Read file content
     */
    public function readFile(string $relativePath, string $filename): ?string
    {
        $filepath = $this->getFullPath($relativePath, $filename);

        if (!$filepath || !is_file($filepath)) {
            return null;
        }

        return file_get_contents($filepath);
    }

    /**
     * Write file content
     */
    public function writeFile(string $relativePath, string $filename, string $content): bool
    {
        $filepath = $this->getFullPath($relativePath, $filename);

        if (!$filepath) {
            return false;
        }

        return file_put_contents($filepath, $content) !== false;
    }

    /**
     * Get file information for preview
     */
    public function getFileInfo(string $relativePath, string $filename): ?array
    {
        $filepath = $this->getFullPath($relativePath, $filename);

        if (!$filepath || !is_file($filepath)) {
            return null;
        }

        $name = basename($filepath);
        $mimeType = mime_content_type($filepath);
        $size = filesize($filepath);

        // Check for HEIC - might not be detected as image by mime_content_type
        $isHeic = $this->isHeicFile($filepath);
        $isImage = strpos($mimeType, 'image/') === 0 || $isHeic;

        return [
            'name' => $name,
            'path' => dirname($filepath),
            'full_path' => $filepath,
            'size' => $size,
            'size_formatted' => \FileManager\Utilities\FileHelper::formatSize($size),
            'mime_type' => $mimeType,
            'modified' => filemtime($filepath),
            'permissions' => substr(sprintf('%o', fileperms($filepath)), -4),
            'is_image' => $isImage,
            'is_heic' => $isHeic,
            'is_text' => $this->isTextFile($mimeType),
        ];
    }

    /**
     * Check if file is text-based
     */
    private function isTextFile(string $mimeType): bool
    {
        $textMimes = [
            'text/',
            'application/json',
            'application/xml',
            'application/javascript',
            'application/x-php',
            'application/x-httpd-php',
        ];

        foreach ($textMimes as $mime) {
            if (strpos($mimeType, $mime) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create ZIP archive from selected files/folders
     */
    public function createZip(array $items, string $relativePath, string $zipName): ?string
    {
        $cleanPath = Validator::cleanPath($relativePath);
        $sourcePath = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');

        if (!is_dir($sourcePath) || !Validator::isWithinRoot($sourcePath, $this->rootPath)) {
            return null;
        }

        // Ensure .zip extension
        if (!str_ends_with($zipName, '.zip')) {
            $zipName .= '.zip';
        }

        $zipPath = $sourcePath . DIRECTORY_SEPARATOR . $zipName;

        // Handle duplicate names
        $counter = 1;
        while (file_exists($zipPath)) {
            $base = str_replace('.zip', '', $zipName);
            $zipPath = $sourcePath . DIRECTORY_SEPARATOR . $base . '_' . $counter . '.zip';
            $counter++;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            return null;
        }

        foreach ($items as $item) {
            if (!Validator::isValidFileName($item)) {
                continue;
            }

            $itemPath = $sourcePath . DIRECTORY_SEPARATOR . $item;

            if (!Validator::isWithinRoot($itemPath, $this->rootPath)) {
                continue;
            }

            if (is_file($itemPath)) {
                $zip->addFile($itemPath, $item);
            } elseif (is_dir($itemPath)) {
                $this->addDirectoryToZip($zip, $itemPath, $item);
            }
        }

        $zip->close();
        return basename($zipPath);
    }

    /**
     * Add directory to ZIP recursively
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $path, string $localPath): void
    {
        $zip->addEmptyDir($localPath);

        $items = @scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            $itemLocalPath = $localPath . '/' . $item;

            if (is_file($itemPath)) {
                $zip->addFile($itemPath, $itemLocalPath);
            } elseif (is_dir($itemPath)) {
                $this->addDirectoryToZip($zip, $itemPath, $itemLocalPath);
            }
        }
    }

    /**
     * Extract ZIP archive with path traversal protection
     */
    public function extractZip(string $relativePath, string $zipName, ?string $targetFolder = null): bool
    {
        $cleanPath = Validator::cleanPath($relativePath);
        $sourceDir = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');
        $zipPath = $sourceDir . DIRECTORY_SEPARATOR . $zipName;

        if (!file_exists($zipPath) || !Validator::isWithinRoot($zipPath, $this->rootPath)) {
            error_log("ExtractZip: Invalid ZIP path or not within root: $zipPath");
            return false;
        }

        $extractToPath = $sourceDir;
        if ($targetFolder) {
            $cleanTarget = Validator::cleanPath($targetFolder);
            if ($cleanTarget) {
                $extractToPath = $sourceDir . DIRECTORY_SEPARATOR . $cleanTarget;
                if (!is_dir($extractToPath)) {
                    if (!mkdir($extractToPath, 0755, true)) {
                        error_log("ExtractZip: Failed to create target directory: $extractToPath");
                        return false;
                    }
                }
            }
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            error_log("ExtractZip: Failed to open ZIP file: $zipPath");
            return false;
        }

        // ZIP slip protection: validate all paths before extraction
        $rootRealPath = realpath($this->rootPath);
        if ($rootRealPath === false) {
            $this->lastError = "Failed to resolve root path";
            return false;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            // Clean the entry name to prevent path traversal
            // Note: We use cleanPath to predict where it would go, checking for ..
            $cleanedName = Validator::cleanPath($entryName);

            // Check if the cleaned name differs significantly (indicates traversal attempt)
            if (str_contains($entryName, '..') || str_contains($entryName, "\0")) {
                $zip->close();
                $this->lastError = "Malicious entry detected: $entryName";
                return false; // Malicious ZIP detected
            }

            // Verify the extracted path would be within the ROOT directory
            // We use the target path as the base, but verify against root for security
            $targetRealPath = realpath($extractToPath);
            if ($targetRealPath === false) {
                // Should exist by now
                $zip->close();
                $this->lastError = "Target path not found: $extractToPath";
                return false;
            }

            // Construct full predicted path
            $fullPath = $targetRealPath . DIRECTORY_SEPARATOR . $cleanedName;

            // Ensure we don't traverse out of ROOT
            if (!str_starts_with($fullPath, $rootRealPath)) {
                $zip->close();
                $this->lastError = "Path traversal out of root detected";
                return false;
            }

            // Collision Check: Cannot extract a file over an existing directory
            // Check if entry is a file (doesn't end in /)
            if (!str_ends_with($entryName, '/')) {
                // Check if destination exists as a directory
                if (is_dir($fullPath)) {
                    $zip->close();
                    $this->lastError = "Collision detected: Cannot extract file '$cleanedName' because a directory with the same name already exists.";
                    return false;
                }
            }
        }

        $result = $zip->extractTo($extractToPath);
        if (!$result) {
            $this->lastError = "Extraction failed (unknown error)";
        }
        $zip->close();

        return $result;
    }

    /**
     * Copy a file or directory to a destination
     */
    public function copyItem(string $sourcePath, string $destinationPath): array
    {
        $cleanSource = Validator::cleanPath($sourcePath);
        $cleanDest = Validator::cleanPath($destinationPath);

        $fullSource = $this->rootPath . ($cleanSource ? DIRECTORY_SEPARATOR . $cleanSource : '');
        $fullDest = $this->rootPath . ($cleanDest ? DIRECTORY_SEPARATOR . $cleanDest : '');

        // Validate paths
        if (
            !Validator::isWithinRoot($fullSource, $this->rootPath) ||
            !Validator::isWithinRoot($fullDest, $this->rootPath)
        ) {
            return ['success' => false, 'message' => 'Invalid path'];
        }

        if (!file_exists($fullSource)) {
            return ['success' => false, 'message' => 'Source does not exist'];
        }

        if (!is_dir($fullDest)) {
            return ['success' => false, 'message' => 'Destination directory does not exist'];
        }

        // Prevent copying a directory into itself or its own subdirectory
        if (is_dir($fullSource) && $this->isSubPathOf($fullDest, $fullSource)) {
            return ['success' => false, 'message' => 'Cannot copy a folder into itself'];
        }

        $sourceName = basename($fullSource);
        $destination = $fullDest . DIRECTORY_SEPARATOR . $sourceName;

        // Check if destination already exists
        if (file_exists($destination)) {
            return ['success' => false, 'message' => 'File or folder already exists at destination'];
        }

        try {
            if (is_dir($fullSource)) {
                $success = $this->copyDirectory($fullSource, $destination);
            } else {
                $success = copy($fullSource, $destination);
            }

            if ($success) {
                return ['success' => true, 'message' => 'Copied successfully'];
            } else {
                return ['success' => false, 'message' => 'Copy failed'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Move a file or directory to a destination
     */
    public function moveItem(string $sourcePath, string $destinationPath): array
    {
        $cleanSource = Validator::cleanPath($sourcePath);
        $cleanDest = Validator::cleanPath($destinationPath);

        $fullSource = $this->rootPath . ($cleanSource ? DIRECTORY_SEPARATOR . $cleanSource : '');
        $fullDest = $this->rootPath . ($cleanDest ? DIRECTORY_SEPARATOR . $cleanDest : '');

        // Validate paths
        if (
            !Validator::isWithinRoot($fullSource, $this->rootPath) ||
            !Validator::isWithinRoot($fullDest, $this->rootPath)
        ) {
            return ['success' => false, 'message' => 'Invalid path'];
        }

        if (!file_exists($fullSource)) {
            return ['success' => false, 'message' => 'Source does not exist'];
        }

        if (!is_dir($fullDest)) {
            return ['success' => false, 'message' => 'Destination directory does not exist'];
        }

        // Prevent moving a directory into itself or its own subdirectory
        if (is_dir($fullSource) && $this->isSubPathOf($fullDest, $fullSource)) {
            return ['success' => false, 'message' => 'Cannot move a folder into itself'];
        }

        $sourceName = basename($fullSource);
        $destination = $fullDest . DIRECTORY_SEPARATOR . $sourceName;

        // Check if destination already exists
        if (file_exists($destination)) {
            return ['success' => false, 'message' => 'File or folder already exists at destination'];
        }

        try {
            if (rename($fullSource, $destination)) {
                return ['success' => true, 'message' => 'Moved successfully'];
            } else {
                return ['success' => false, 'message' => 'Move failed'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Copy multiple items to a destination
     * Returns detailed results for each item (supports partial failures)
     *
     * @param array $items Array of item names to copy
     * @param string $sourcePath Source directory path
     * @param string $destinationPath Destination directory path
     * @return array Results with success count, failures, and per-item details
     */
    public function copyMultiple(array $items, string $sourcePath, string $destinationPath): array
    {
        $results = [
            'success' => true,
            'successCount' => 0,
            'failureCount' => 0,
            'items' => [],
            'message' => ''
        ];

        foreach ($items as $item) {
            $itemPath = $sourcePath ? $sourcePath . '/' . $item : $item;
            $result = $this->copyItem($itemPath, $destinationPath);

            $results['items'][] = [
                'name' => $item,
                'success' => $result['success'],
                'message' => $result['message']
            ];

            if ($result['success']) {
                $results['successCount']++;
            } else {
                $results['failureCount']++;
            }
        }

        // Set overall success and message
        $total = count($items);
        $results['success'] = $results['successCount'] > 0;

        if ($results['failureCount'] === 0) {
            $results['message'] = "Copied $total item(s) successfully";
        } elseif ($results['successCount'] === 0) {
            $results['success'] = false;
            $results['message'] = "Failed to copy all $total item(s)";
        } else {
            $results['message'] = "Copied {$results['successCount']} of $total item(s). {$results['failureCount']} failed.";
        }

        return $results;
    }

    /**
     * Move multiple items to a destination
     * Returns detailed results for each item (supports partial failures)
     *
     * @param array $items Array of item names to move
     * @param string $sourcePath Source directory path
     * @param string $destinationPath Destination directory path
     * @return array Results with success count, failures, and per-item details
     */
    public function moveMultiple(array $items, string $sourcePath, string $destinationPath): array
    {
        $results = [
            'success' => true,
            'successCount' => 0,
            'failureCount' => 0,
            'items' => [],
            'message' => ''
        ];

        foreach ($items as $item) {
            $itemPath = $sourcePath ? $sourcePath . '/' . $item : $item;
            $result = $this->moveItem($itemPath, $destinationPath);

            $results['items'][] = [
                'name' => $item,
                'success' => $result['success'],
                'message' => $result['message']
            ];

            if ($result['success']) {
                $results['successCount']++;
            } else {
                $results['failureCount']++;
            }
        }

        // Set overall success and message
        $total = count($items);
        $results['success'] = $results['successCount'] > 0;

        if ($results['failureCount'] === 0) {
            $results['message'] = "Moved $total item(s) successfully";
        } elseif ($results['successCount'] === 0) {
            $results['success'] = false;
            $results['message'] = "Failed to move all $total item(s)";
        } else {
            $results['message'] = "Moved {$results['successCount']} of $total item(s). {$results['failureCount']} failed.";
        }

        return $results;
    }

    /**
     * Delete multiple items
     * Returns detailed results for each item (supports partial failures)
     *
     * @param array $items Array of item names to delete
     * @param string $path Directory path containing the items
     * @return array Results with success count, failures, and per-item details
     */
    public function deleteMultiple(array $items, string $path): array
    {
        $results = [
            'success' => true,
            'successCount' => 0,
            'failureCount' => 0,
            'items' => [],
            'message' => ''
        ];

        foreach ($items as $item) {
            $success = $this->delete($path, $item);

            $results['items'][] = [
                'name' => $item,
                'success' => $success,
                'message' => $success ? 'Deleted' : 'Failed to delete'
            ];

            if ($success) {
                $results['successCount']++;
            } else {
                $results['failureCount']++;
            }
        }

        // Set overall success and message
        $total = count($items);
        $results['success'] = $results['successCount'] > 0;

        if ($results['failureCount'] === 0) {
            $results['message'] = "Deleted $total item(s) successfully";
        } elseif ($results['successCount'] === 0) {
            $results['success'] = false;
            $results['message'] = "Failed to delete all $total item(s)";
        } else {
            $results['message'] = "Deleted {$results['successCount']} of $total item(s). {$results['failureCount']} failed.";
        }

        return $results;
    }

    // ============================================
    // HEIC Image Support
    // ============================================

    /**
     * Check if a file is a HEIC image
     */
    public function isHeicFile(string $filepath): bool
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        return in_array($extension, ['heic', 'heif']);
    }

    /**
     * Get dimensions of a HEIC image using sips (macOS) or ImageMagick
     *
     * @param string $filepath Full path to the HEIC file
     * @return array|null [width, height] or null on failure
     */
    public function getHeicDimensions(string $filepath): ?array
    {
        if (!file_exists($filepath)) {
            return null;
        }

        // Try sips first (faster on macOS)
        if (PHP_OS_FAMILY === 'Darwin' && file_exists('/usr/bin/sips')) {
            $output = [];
            $escapedPath = escapeshellarg($filepath);
            exec("/usr/bin/sips -g pixelWidth -g pixelHeight {$escapedPath} 2>/dev/null", $output);

            $width = null;
            $height = null;
            foreach ($output as $line) {
                if (preg_match('/pixelWidth:\s*(\d+)/', $line, $m)) {
                    $width = (int)$m[1];
                }
                if (preg_match('/pixelHeight:\s*(\d+)/', $line, $m)) {
                    $height = (int)$m[1];
                }
            }

            if ($width && $height) {
                return [$width, $height];
            }
        }

        // Fallback to ImageMagick (supports macOS Homebrew, Linux apt, and custom installs)
        // ImageMagick 7 uses `magick`, ImageMagick 6 (Ubuntu default) uses `convert`
        $magickPaths = [
            '/opt/homebrew/bin/magick',  // macOS Homebrew
            '/usr/local/bin/magick',     // macOS/Linux custom install
            '/usr/bin/magick',           // Linux ImageMagick 7
            '/usr/bin/convert',          // Linux ImageMagick 6 (Ubuntu apt default)
        ];
        $magickPath = null;
        foreach ($magickPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $magickPath = $path;
                break;
            }
        }

        // Last resort: ask the shell to find magick/convert in PATH
        if (!$magickPath) {
            $found = trim((string)shell_exec('which magick 2>/dev/null || which convert 2>/dev/null'));
            if ($found && is_executable($found)) {
                $magickPath = $found;
            }
        }

        if ($magickPath) {
            $escapedMagick = escapeshellarg($magickPath);
            $escapedPath = escapeshellarg($filepath);
            // ImageMagick 6 `convert` uses the same identify syntax via `magick identify` or standalone `identify`
            $identifyCmd = basename($magickPath) === 'convert'
                ? escapeshellarg(dirname($magickPath) . '/identify')
                : "{$escapedMagick} identify";
            $output = shell_exec("{$identifyCmd} -format \"%wx%h\" {$escapedPath} 2>/dev/null");
            if ($output && preg_match('/(\d+)x(\d+)/', trim($output), $m)) {
                return [(int)$m[1], (int)$m[2]];
            }
        }

        return null;
    }

    /**
     * Convert HEIC image to JPEG for browser display
     *
     * @param string $filepath Full path to the HEIC file
     * @return string|null JPEG content or null on failure
     */
    public function convertHeicToJpeg(string $filepath): ?string
    {
        if (!file_exists($filepath)) {
            return null;
        }

        // Create a temp file for the output
        $tempFile = sys_get_temp_dir() . '/heic_' . md5($filepath) . '_' . time() . '.jpg';

        // Try sips first (macOS native, faster)
        if (PHP_OS_FAMILY === 'Darwin' && file_exists('/usr/bin/sips')) {
            $escapedInput = escapeshellarg($filepath);
            $escapedOutput = escapeshellarg($tempFile);
            exec("/usr/bin/sips -s format jpeg {$escapedInput} --out {$escapedOutput} 2>/dev/null", $output, $returnCode);

            if ($returnCode === 0 && file_exists($tempFile)) {
                $content = file_get_contents($tempFile);
                @unlink($tempFile);
                return $content !== false ? $content : null;
            }
        }

        // Fallback to ImageMagick (supports macOS Homebrew, Linux apt, and custom installs)
        // ImageMagick 7 uses `magick`, ImageMagick 6 (Ubuntu apt default) uses `convert`
        $magickPaths = [
            '/opt/homebrew/bin/magick',  // macOS Homebrew
            '/usr/local/bin/magick',     // macOS/Linux custom install
            '/usr/bin/magick',           // Linux ImageMagick 7
            '/usr/bin/convert',          // Linux ImageMagick 6 (Ubuntu apt default)
        ];
        $magickPath = null;
        foreach ($magickPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $magickPath = $path;
                break;
            }
        }

        // Last resort: ask the shell to find magick/convert in PATH
        if (!$magickPath) {
            $found = trim((string)shell_exec('which magick 2>/dev/null || which convert 2>/dev/null'));
            if ($found && is_executable($found)) {
                $magickPath = $found;
            }
        }

        if ($magickPath) {
            $escapedInput = escapeshellarg($filepath);
            $escapedOutput = escapeshellarg($tempFile);
            exec("{$magickPath} {$escapedInput} -quality 90 {$escapedOutput} 2>/dev/null", $output, $returnCode);

            if ($returnCode === 0 && file_exists($tempFile)) {
                $content = file_get_contents($tempFile);
                @unlink($tempFile);
                return $content !== false ? $content : null;
            }
        }

        return null;
    }

    /**
     * Get image dimensions, with HEIC support
     *
     * @param string $filepath Full path to the image
     * @return array|null [width, height, type, attr] similar to getimagesize() or null
     */
    public function getImageDimensions(string $filepath): ?array
    {
        // Try standard getimagesize first
        $info = @getimagesize($filepath);
        if ($info !== false) {
            return $info;
        }

        // If standard method fails, try HEIC-specific
        if ($this->isHeicFile($filepath)) {
            $dims = $this->getHeicDimensions($filepath);
            if ($dims) {
                return [
                    $dims[0],
                    $dims[1],
                    IMAGETYPE_UNKNOWN,
                    'width="' . $dims[0] . '" height="' . $dims[1] . '"'
                ];
            }
        }

        return null;
    }
}
