<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: Validator.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:43:46 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Security;

/**
 * Security Validator
 * Validates and sanitizes user inputs, prevents path traversal
 */
class Validator
{
    /**
     * Clean and validate path to prevent directory traversal
     */
    public static function cleanPath(string $path): string
    {
        $path = trim($path);
        $path = trim($path, '\\/');

        // Remove null bytes
        $path = str_replace("\0", '', $path);

        // Normalize slashes first
        $path = str_replace('\\', '/', $path);

        // Iteratively remove path traversal sequences until none remain
        // This prevents bypasses like ....// becoming ../
        do {
            $original = $path;
            $path = str_replace(['../', '..'], '', $path);
        } while ($path !== $original);

        // Remove multiple consecutive slashes
        $path = preg_replace('#/+#', '/', $path);

        // Final trim of any remaining leading/trailing slashes
        $path = trim($path, '/');

        // Handle current directory (.) - should be treated as root/empty
        if ($path === '.') {
            $path = '';
        }

        return $path;
    }

    /**
     * Validate file name (no path separators)
     */
    public static function isValidFileName(string $filename): bool
    {
        if (empty($filename) || $filename === '.' || $filename === '..') {
            return false;
        }

        // Check for path separators
        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            return false;
        }

        // Check for null bytes
        if (str_contains($filename, "\0")) {
            return false;
        }

        return true;
    }

    /**
     * Validate file extension against allowed list
     */
    public static function isAllowedExtension(string $filename, array $allowedExtensions): bool
    {
        // Allow all if '*' is in the list
        if (in_array('*', $allowedExtensions, true)) {
            return true;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, array_map('strtolower', $allowedExtensions), true);
    }

    /**
     * Validate file size
     */
    public static function isValidFileSize(int $size, int $maxSize): bool
    {
        return $size > 0 && $size <= $maxSize;
    }

    /**
     * Sanitize string for output (prevent XSS)
     */
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Validate that path is within allowed root
     */
    public static function isWithinRoot(string $path, string $root): bool
    {
        $realPath = realpath($path);
        $realRoot = realpath($root);

        if ($realPath === false || $realRoot === false) {
            return false;
        }

        // Ensure path starts with root directory
        return str_starts_with($realPath, $realRoot);
    }

    /**
     * Sanitize string for use in filenames
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove any path components
        $filename = basename($filename);

        // Remove special characters but keep basic ones
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove leading dots (hidden files)
        $filename = ltrim($filename, '.');

        return $filename;
    }
}
