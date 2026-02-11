<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: FileHelper.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:44:02 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Utilities;

/**
 * File Helper Utilities
 * Helper functions for file operations
 */
class FileHelper
{
    /**
     * Format file size to human-readable format
     */
    public static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);

        return sprintf("%.2f %s", $bytes / (1024 ** $factor), $units[$factor]);
    }

    /**
     * Get file extension
     */
    public static function getExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Get MIME type of file
     */
    public static function getMimeType(string $filepath): string
    {
        if (!file_exists($filepath)) {
            return 'application/octet-stream';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * Get icon class based on file type
     */
    public static function getFileIcon(string $filename): string
    {
        $ext = self::getExtension($filename);

        return match ($ext) {
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp' => 'fa-file-image',
            'mp4', 'avi', 'mov', 'mkv', 'webm' => 'fa-file-video',
            'mp3', 'wav', 'ogg', 'flac' => 'fa-file-audio',
            'pdf' => 'fa-file-pdf',
            'doc', 'docx' => 'fa-file-word',
            'xls', 'xlsx' => 'fa-file-excel',
            'ppt', 'pptx' => 'fa-file-powerpoint',
            'zip', 'rar', '7z', 'tar', 'gz' => 'fa-file-zipper',
            'txt', 'md', 'log' => 'fa-file-lines',
            'php', 'js', 'py', 'java', 'c', 'cpp', 'css', 'html' => 'fa-file-code',
            default => 'fa-file',
        };
    }

    /**
     * Check if file is an image
     */
    public static function isImage(string $filepath): bool
    {
        $mimeType = self::getMimeType($filepath);
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Check if file is a video
     */
    public static function isVideo(string $filepath): bool
    {
        $mimeType = self::getMimeType($filepath);
        return str_starts_with($mimeType, 'video/');
    }

    /**
     * Check if file is audio
     */
    public static function isAudio(string $filepath): bool
    {
        $mimeType = self::getMimeType($filepath);
        return str_starts_with($mimeType, 'audio/');
    }

    /**
     * Check if file is text/editable
     */
    public static function isText(string $filepath): bool
    {
        $mimeType = self::getMimeType($filepath);
        $ext = self::getExtension($filepath);

        $textExtensions = ['txt', 'md', 'json', 'xml', 'yaml', 'yml', 'ini', 'conf', 'log'];

        return str_starts_with($mimeType, 'text/') || in_array($ext, $textExtensions, true);
    }

    /**
     * Normalize path separators
     */
    public static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Get file modification time formatted
     */
    public static function getFormattedTime(string $filepath, string $format = 'Y-m-d H:i:s'): string
    {
        if (!file_exists($filepath)) {
            return '';
        }

        return date($format, filemtime($filepath));
    }
}
