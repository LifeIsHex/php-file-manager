<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: DirectoryManager.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:42:14 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\FileManager;

use FileManager\Security\Validator;
use FileManager\Utilities\FileHelper;

/**
 * Directory Manager
 * Handles directory operations and navigation
 */
readonly class DirectoryManager
{
    public function __construct(
        private string $rootPath,
        private array  $excludeItems = [],
        private array  $config = [],
    )
    {
    }

    /**
     * List directory contents
     */
    public function listContents(string $relativePath = ''): array
    {
        $cleanPath = Validator::cleanPath($relativePath);
        $fullPath = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');

        if (!is_dir($fullPath) || !Validator::isWithinRoot($fullPath, $this->rootPath)) {
            return [];
        }

        $items = scandir($fullPath);
        if ($items === false) {
            return [];
        }

        $result = ['directories' => [], 'files' => []];

        foreach ($items as $item) {
            // Skip . and .. and excluded items
            if ($item === '.' || $item === '..' || in_array($item, $this->excludeItems, true)) {
                continue;
            }

            // Skip hidden files if show_hidden is false
            if (!($this->config['fm']['show_hidden'] ?? true) && str_starts_with($item, '.')) {
                continue;
            }

            $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($itemPath)) {
                $result['directories'][] = $this->getItemInfo($itemPath, $item, 'directory');
            } else {
                $result['files'][] = $this->getItemInfo($itemPath, $item, 'file');
            }
        }

        // Sort alphabetically
        usort($result['directories'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($result['files'], fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * Get information about a file or directory
     */
    private function getItemInfo(string $path, string $name, string $type): array
    {
        $info = [
            'name' => $name,
            'type' => $type,
            'modified' => filemtime($path),
            'permissions' => substr(sprintf('%o', fileperms($path)), -4),
        ];

        // Get owner information
        $owner = 'unknown';
        if (function_exists('posix_getpwuid')) {
            $ownerInfo = @posix_getpwuid(fileowner($path));
            if ($ownerInfo) {
                $owner = $ownerInfo['name'];
            }
        }
        $info['owner'] = $owner;

        if ($type === 'file') {
            $info['size'] = filesize($path);
            $info['size_formatted'] = FileHelper::formatSize($info['size']);
            $info['extension'] = FileHelper::getExtension($name);
            $info['icon'] = FileHelper::getFileIcon($name);
        } else {
            $info['size'] = 0;
            $info['size_formatted'] = FileHelper::formatSize(0);
            $info['icon'] = 'fa-folder';
        }

        return $info;
    }

    /**
     * Create a new directory
     */
    public function createDirectory(string $relativePath, string $name): bool
    {
        if (!Validator::isValidFileName($name)) {
            return false;
        }

        $cleanPath = Validator::cleanPath($relativePath);
        $parentPath = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');
        $newDirPath = $parentPath . DIRECTORY_SEPARATOR . $name;

        if (!Validator::isWithinRoot($parentPath, $this->rootPath)) {
            return false;
        }

        if (file_exists($newDirPath)) {
            return false;
        }

        return mkdir($newDirPath, 0755, true);
    }

    /**
     * Delete a directory recursively
     */
    public function deleteDirectory(string $path): bool
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
     * Get breadcrumb path array
     */
    public function getBreadcrumbs(string $relativePath): array
    {
        $cleanPath = Validator::cleanPath($relativePath);

        if (empty($cleanPath)) {
            return [['name' => 'Home', 'path' => '']];
        }

        $parts = explode('/', $cleanPath);
        $breadcrumbs = [['name' => 'Home', 'path' => '']];
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= ($currentPath ? '/' : '') . $part;
            $breadcrumbs[] = ['name' => $part, 'path' => $currentPath];
        }

        return $breadcrumbs;
    }

    /**
     * Get statistics for the current directory
     *
     * @param string $relativePath
     * @return array
     */
    public function getStatistics(string $relativePath): array
    {
        $contents = $this->listContents($relativePath);

        $totalFiles = count($contents['files']);
        $totalDirectories = count($contents['directories']);
        $totalSize = 0;

        // Calculate total size from files
        foreach ($contents['files'] as $file) {
            $totalSize += $file['size'];
        }

        return [
            'totalFiles' => $totalFiles,
            'totalDirectories' => $totalDirectories,
            'totalSize' => $totalSize,
            'totalSizeFormatted' => FileHelper::formatSize($totalSize)
        ];
    }

    /**
     * Search for files and directories
     */
    public function search(string $query, string $relativePath = ''): array
    {
        if (empty($query)) {
            return ['directories' => [], 'files' => []];
        }

        $cleanPath = Validator::cleanPath($relativePath);
        $fullPath = $this->rootPath . ($cleanPath ? DIRECTORY_SEPARATOR . $cleanPath : '');

        if (!is_dir($fullPath) || !Validator::isWithinRoot($fullPath, $this->rootPath)) {
            return ['directories' => [], 'files' => []];
        }

        $query = strtolower($query);
        $result = ['directories' => [], 'files' => []];

        $this->searchRecursive($fullPath, $query, $result, $cleanPath);

        usort($result['directories'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($result['files'], fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * Recursive search helper
     */
    private function searchRecursive(string $path, string $query, array &$result, string $relativePath, int $depth = 0): void
    {
        if ($depth > 5) {
            return;
        }

        $items = @scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || in_array($item, $this->excludeItems, true)) {
                continue;
            }

            // Skip hidden files if show_hidden is false
            if (!($this->config['fm']['show_hidden'] ?? true) && str_starts_with($item, '.')) {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;

            if (str_contains(strtolower($item), $query)) {
                if (is_dir($itemPath)) {
                    $info = $this->getItemInfo($itemPath, $item, 'directory');
                    $info['path'] = $relativePath;
                    $result['directories'][] = $info;
                } else {
                    $info = $this->getItemInfo($itemPath, $item, 'file');
                    $info['path'] = $relativePath;
                    $result['files'][] = $info;
                }
            }

            if (is_dir($itemPath)) {
                $subRelativePath = $relativePath ? $relativePath . '/' . $item : $item;
                $this->searchRecursive($itemPath, $query, $result, $subRelativePath, $depth + 1);
            }
        }
    }

    /**
     * Get the root path
     */
    public function getRootPath(): string
    {
        return $this->rootPath;
    }
}
