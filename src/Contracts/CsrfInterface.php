<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: CsrfInterface.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:41:43 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Contracts;

/**
 * CSRF Protection Interface
 *
 * Implement this interface to use your own CSRF protection system.
 * Useful for CI4 integration where CI4 manages CSRF tokens.
 *
 * Example CI4 implementation:
 * ```php
 * class CI4CsrfAdapter implements CsrfInterface {
 *     public function getToken(): string {
 *         return csrf_hash();
 *     }
 *     public function getTokenName(): string {
 *         return csrf_token();
 *     }
 *     public function validateToken(string $token): bool {
 *         return hash_equals(csrf_hash(), $token);
 *     }
 *     public function getTokenField(): string {
 *         return csrf_field();
 *     }
 * }
 * ```
 */
interface CsrfInterface
{
    /**
     * Get the current CSRF token value
     */
    public function getToken(): string;

    /**
     * Validate a CSRF token
     */
    public function validateToken(string $token): bool;

    /**
     * Get the HTML hidden input field with CSRF token
     */
    public function getTokenField(): string;
}
