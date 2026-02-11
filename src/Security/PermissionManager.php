<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: PermissionManager.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:43:39 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Security;

/**
 * PermissionManager
 *
 * Manages role-based permissions for file manager actions.
 * Roles are defined in config and map to allowed actions.
 *
 * Available actions:
 *   upload, download, delete, rename, new_folder, copy, move,
 *   view, view_pdf, extract, zip, permissions
 *
 * @example
 * ```php
 * $pm = new PermissionManager($config);
 * $pm->setRole('viewer');
 * if ($pm->can('delete')) { ... }
 * ```
 */
class PermissionManager
{
    /** All available actions */
    private const ALL_ACTIONS = [
        'upload', 'download', 'delete', 'rename', 'new_folder',
        'copy', 'move', 'view', 'view_pdf', 'extract', 'zip', 'permissions',
    ];

    private string $role;
    private array $roles;

    public function __construct(array $config)
    {
        $permConfig = $config['permissions'] ?? [];
        $this->roles = $permConfig['roles'] ?? [
            'admin' => ['*'],
        ];
        $this->role = $permConfig['default_role'] ?? 'admin';
    }

    /**
     * Check if the current role allows a specific action
     */
    public function can(string $action): bool
    {
        $allowed = $this->roles[$this->role] ?? [];

        // Wildcard means all actions
        if (in_array('*', $allowed, true)) {
            return true;
        }

        return in_array($action, $allowed, true);
    }

    /**
     * Get the current role name
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Set the active role
     *
     * @throws \InvalidArgumentException if role doesn't exist in config
     */
    public function setRole(string $role): self
    {
        if (!isset($this->roles[$role])) {
            throw new \InvalidArgumentException("Unknown role: '$role'. Available roles: " . implode(', ', array_keys($this->roles)));
        }
        $this->role = $role;
        return $this;
    }

    /**
     * Get list of all allowed actions for the current role
     */
    public function getAllowedActions(): array
    {
        $allowed = $this->roles[$this->role] ?? [];

        if (in_array('*', $allowed, true)) {
            return self::ALL_ACTIONS;
        }

        return array_values(array_intersect($allowed, self::ALL_ACTIONS));
    }

    /**
     * Get all available roles
     *
     * @return array<string, array<string>>
     */
    public function getAvailableRoles(): array
    {
        return $this->roles;
    }

    /**
     * Get all possible actions
     */
    public static function getAllActions(): array
    {
        return self::ALL_ACTIONS;
    }
}
