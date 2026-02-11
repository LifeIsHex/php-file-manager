<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: SessionManager.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:44:35 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Utilities;

/**
 * Session Manager
 * Handles session-based data storage for file operations
 */
class SessionManager
{
    private const string PENDING_OP_KEY = 'pending_file_operation';
    private const string FLASH_MSG_KEY = 'flash_message';

    /**
     * Set a pending file operation in session
     */
    public function setPendingOperation(array $data): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::PENDING_OP_KEY] = array_merge($data, [
            'timestamp' => time()
        ]);
    }

    /**
     * Get pending file operation from session
     */
    public function getPendingOperation(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::PENDING_OP_KEY])) {
            return null;
        }

        $operation = $_SESSION[self::PENDING_OP_KEY];

        // Check if operation is expired (older than 1 hour)
        if (isset($operation['timestamp']) && (time() - $operation['timestamp']) > 3600) {
            $this->clearPendingOperation();
            return null;
        }

        return $operation;
    }

    /**
     * Clear pending operation from session
     */
    public function clearPendingOperation(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::PENDING_OP_KEY]);
    }

    /**
     * Set a flash message to display on next page load
     */
    public function setFlashMessage(string $type, string $text): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::FLASH_MSG_KEY] = [
            'type' => $type,
            'text' => $text
        ];
    }

    /**
     * Get and clear flash message
     */
    public function getFlashMessage(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::FLASH_MSG_KEY])) {
            return null;
        }

        $message = $_SESSION[self::FLASH_MSG_KEY];
        unset($_SESSION[self::FLASH_MSG_KEY]);

        return $message;
    }
}
