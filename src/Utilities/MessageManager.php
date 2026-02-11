<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: MessageManager.php
 *
 * Last Modified: Tue, 10 Feb 2026 - 18:44:21 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Utilities;

/**
 * Message Manager
 * Handles flash messages for user feedback
 */
class MessageManager
{
    private const string SESSION_KEY = 'fm_messages';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Add a message to the queue
     */
    public function add(string $message, string $type = 'info'): void
    {
        $_SESSION[self::SESSION_KEY][] = [
            'message' => $message,
            'type' => $type,
            'time' => time(),
        ];
    }

    /**
     * Add success message
     */
    public function success(string $message): void
    {
        $this->add($message, 'success');
    }

    /**
     * Add error message
     */
    public function error(string $message): void
    {
        $this->add($message, 'danger');
    }

    /**
     * Add warning message
     */
    public function warning(string $message): void
    {
        $this->add($message, 'warning');
    }

    /**
     * Add info message
     */
    public function info(string $message): void
    {
        $this->add($message, 'info');
    }

    /**
     * Get all messages and clear them
     */
    public function getAll(): array
    {
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);
        return $messages;
    }

    /**
     * Check if there are any messages
     */
    public function hasMessages(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }
}
