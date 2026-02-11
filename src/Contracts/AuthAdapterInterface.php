<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: AuthAdapterInterface.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:41:30 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Contracts;

/**
 * Authentication Adapter Interface
 *
 * Implement this interface to use your own authentication system.
 * Useful for CI4 integration where you have existing auth.
 *
 * Example CI4 implementation:
 * ```php
 * class CI4AuthAdapter implements AuthAdapterInterface {
 *     public function isAuthenticated(): bool {
 *         return session()->has('user_id');
 *     }
 *     public function getUsername(): ?string {
 *         return session()->get('username');
 *     }
 *     public function getUserId(): ?int {
 *         return session()->get('user_id');
 *     }
 *     public function getUserRole(): ?string {
 *         return session()->get('role') ?? 'user';
 *     }
 * }
 * ```
 */
interface AuthAdapterInterface
{
    /**
     * Check if the current user is authenticated
     */
    public function isAuthenticated(): bool;

    /**
     * Get the username of the authenticated user
     */
    public function getUsername(): ?string;

    /**
     * Get the user ID of the authenticated user
     */
    public function getUserId(): ?int;

    /**
     * Get the role of the authenticated user
     * Used for permission-based access control
     */
    public function getUserRole(): ?string;
}
