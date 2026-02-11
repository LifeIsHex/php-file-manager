<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: Request.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:42:37 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Http;

/**
 * HTTP Request Handler
 * Parses and sanitizes HTTP requests
 */
class Request
{
    private array $get;
    private array $post;
    private array $files;
    private string $method;
    private array $pathSegments;
    private string $pathInfo;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Parse path info for URL-based routing (e.g., /file-manager/120)
        $this->pathInfo = $_SERVER['PATH_INFO'] ?? '';
        $this->pathSegments = $this->parsePathSegments();
    }

    /**
     * Parse path segments from PATH_INFO or REQUEST_URI
     */
    private function parsePathSegments(): array
    {
        // Try PATH_INFO first (cleanest)
        if (!empty($this->pathInfo)) {
            return array_values(array_filter(explode('/', trim($this->pathInfo, '/'))));
        }

        // Fallback: parse REQUEST_URI
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '';
        $segments = array_filter(explode('/', trim($path, '/')));

        // Skip common known segments
        $skipSegments = ['file-manager', 'index.php', 'filemanager'];
        $result = [];
        $foundEntry = false;

        foreach ($segments as $segment) {
            if (!$foundEntry && in_array(strtolower($segment), $skipSegments, true)) {
                $foundEntry = true;
                continue;
            }
            if ($foundEntry) {
                $result[] = $segment;
            }
        }

        return array_values($result);
    }

    /**
     * Get value from GET parameters
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Get value from POST parameters
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Get uploaded files
     */
    public function files(string $key = 'upload'): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Check if request is POST
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Check if request is GET
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Get all GET parameters
     */
    public function allGet(): array
    {
        return $this->get;
    }

    /**
     * Get all POST parameters
     */
    public function allPost(): array
    {
        return $this->post;
    }

    /**
     * Get a path segment by index
     *
     * For URL /file-manager/120/subfolder:
     * - getPathSegment(0) = '120'
     * - getPathSegment(1) = 'subfolder'
     */
    public function getPathSegment(int $index): ?string
    {
        return $this->pathSegments[$index] ?? null;
    }

    /**
     * Get all path segments
     */
    public function getPathSegments(): array
    {
        return $this->pathSegments;
    }

    /**
     * Get folder ID from URL path or query parameter
     *
     * Tries path segment first, then falls back to query param
     *
     * @param string $paramName The query parameter name (default: 'folder')
     * @return string|null The folder ID or null
     */
    public function getFolderId(string $paramName = 'folder'): ?string
    {
        // First path segment is the folder ID
        $folderId = $this->getPathSegment(0);

        if ($folderId !== null && $folderId !== '') {
            return $folderId;
        }

        // Fallback to query parameter
        $queryFolderId = $this->get($paramName);

        return $queryFolderId !== null && $queryFolderId !== ''
            ? (string)$queryFolderId
            : null;
    }

    /**
     * Get the raw PATH_INFO
     */
    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }
}
