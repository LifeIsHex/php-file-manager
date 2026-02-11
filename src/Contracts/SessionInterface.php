<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: SessionInterface.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:42:00 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Contracts;

/**
 * Session Interface
 *
 * Implement this interface to provide your own session handling.
 * Useful for CI4 integration where CI4 manages sessions.
 */
interface SessionInterface
{
    /**
     * Get a value from the session
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a value in the session
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a key exists in the session
     */
    public function has(string $key): bool;

    /**
     * Remove a key from the session
     */
    public function remove(string $key): void;

    /**
     * Regenerate the session ID (for security after login)
     */
    public function regenerate(): void;

    /**
     * Destroy the entire session
     */
    public function destroy(): void;
}
