<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: CsrfProtection.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:43:28 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Security;

use FileManager\Contracts\CsrfInterface;

/**
 * CSRF Protection
 * Generates and validates CSRF tokens for form submissions
 */
class CsrfProtection implements CsrfInterface
{
    private const TOKEN_KEY = 'fm_csrf_token';

    /**
     * Generate a new CSRF token
     */
    public function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_KEY] = $token;

        return $token;
    }

    /**
     * Get current CSRF token (generate if not exists)
     */
    public function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION[self::TOKEN_KEY] ?? $this->generateToken();
    }

    /**
     * Validate CSRF token
     */
    public function validateToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? null;

        if ($sessionToken === null) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($sessionToken, $token);
    }

    /**
     * Generate HTML hidden input field with CSRF token
     */
    public function getTokenField(): string
    {
        $token = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');
        return sprintf('<input type="hidden" name="csrf_token" value="%s">', $token);
    }
}
