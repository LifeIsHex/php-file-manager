<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: CI4CsrfAdapter.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:40:38 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Adapters;

use FileManager\Contracts\CsrfInterface;

/**
 * CI4 CSRF Adapter
 *
 * Example implementation for CodeIgniter 4 integration.
 * Uses CI4's built-in CSRF protection.
 *
 * NOTE: The csrf_hash(), csrf_field() functions are CI4 helpers that
 * are only available when running within a CodeIgniter 4 application.
 * This adapter is intended to be copied to your CI4 project, not used
 * directly from this package.
 *
 * Usage in your CI4 controller:
 * ```php
 * $csrfAdapter = new CI4CsrfAdapter();
 * // Pass to file manager initialization
 * ```
 */
class CI4CsrfAdapter implements CsrfInterface
{
    /**
     * Get the current CSRF token value
     */
    public function getToken(): string
    {
        return csrf_hash();
    }

    /**
     * Validate a CSRF token
     */
    public function validateToken(string $token): bool
    {
        return hash_equals(csrf_hash(), $token);
    }

    /**
     * Get the HTML hidden input field with CSRF token
     */
    public function getTokenField(): string
    {
        return csrf_field();
    }
}
