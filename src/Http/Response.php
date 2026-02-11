<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: Response.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:42:47 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Http;

/**
 * HTTP Response Handler
 * Handles different types of HTTP responses
 */
class Response
{
    /**
     * Send JSON response
     */
    public static function json(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirect to a URL
     */
    public static function redirect(string $url, int $statusCode = 302): never
    {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }

    /**
     * Send file download response
     */
    public static function download(string $filepath, string $filename): never
    {
        if (!file_exists($filepath)) {
            http_response_code(404);
            exit('File not found');
        }

        // Sanitize filename to prevent header injection
        $safeFilename = self::sanitizeHeaderFilename($filename);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache');

        readfile($filepath);
        exit;
    }

    /**
     * Sanitize filename for use in HTTP headers
     * Prevents header injection attacks
     */
    private static function sanitizeHeaderFilename(string $filename): string
    {
        // Remove any newlines, carriage returns, and null bytes
        $filename = str_replace(["\r", "\n", "\0"], '', $filename);
        // Remove or encode special characters
        $filename = preg_replace('/[^\x20-\x7E]/', '', $filename);
        // Escape quotes
        $filename = str_replace('"', '\\"', $filename);
        return $filename;
    }

    /**
     * Send error response
     */
    public static function error(string $message, int $statusCode = 400): never
    {
        http_response_code($statusCode);
        echo $message;
        exit;
    }
}
