<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: CI4AuthAdapter.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:40:32 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Adapters;

use FileManager\Contracts\AuthAdapterInterface;

/**
 * CI4 Authentication Adapter
 *
 * Example implementation for CodeIgniter 4 integration.
 * Copy this file to your CI4 app and modify as needed.
 *
 * NOTE: The session() function is a CI4 helper that is only available
 * when running within a CodeIgniter 4 application. This adapter is
 * intended to be copied to your CI4 project, not used directly from
 * this package.
 *
 * Usage in your CI4 controller:
 * ```php
 * $authAdapter = new CI4AuthAdapter();
 * $config['auth']['adapter'] = $authAdapter;
 * ```
 */
class CI4AuthAdapter implements AuthAdapterInterface
{
    /**
     * Check if the current user is authenticated
     */
    public function isAuthenticated(): bool
    {
        // Modify this to match your CI4 auth system
        return session()->has('user_id') && session()->get('user_id') !== null;
    }

    /**
     * Get the username of the authenticated user
     */
    public function getUsername(): ?string
    {
        return session()->get('username');
    }

    /**
     * Get the user ID of the authenticated user
     */
    public function getUserId(): ?int
    {
        $userId = session()->get('user_id');
        return $userId !== null ? (int)$userId : null;
    }

    /**
     * Get the role of the authenticated user
     */
    public function getUserRole(): ?string
    {
        return session()->get('role') ?? 'user';
    }
}
