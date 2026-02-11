<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: User.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:41:19 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Auth;

/**
 * User Model
 * Represents an authenticated user in the system
 */
readonly class User
{
    public function __construct(
        public string $username,
        public string $passwordHash,
    )
    {
    }

    /**
     * Verify if provided password matches the hash
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /**
     * Check if password needs rehashing (algorithm updated)
     */
    public function needsRehash(): bool
    {
        return password_needs_rehash($this->passwordHash, PASSWORD_ARGON2ID);
    }

    /**
     * Get new password hash
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
}
