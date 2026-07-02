<?php
/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: AuthManager.php
 *
 * Last Modified: Thu, 2 Jul 2026 - 10:14:54 MDT (-0600)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FileManager\Auth;

use FileManager\Security\CsrfProtection;

/**
 * Authentication Manager
 * Handles user authentication, sessions, and login attempts
 */
class AuthManager
{
    private const string LOGIN_ATTEMPTS_KEY = 'fm_login_attempts';
    private const string LAST_ATTEMPT_KEY = 'fm_last_attempt';
    private const string REMEMBER_COOKIE_NAME = 'fm_remember';
    private const string REMEMBER_TOKEN_KEY = 'fm_remember_token';

    public function __construct(
        private readonly array          $config,
        private readonly CsrfProtection $csrf,
    )
    {
        $this->initSession();
    }

    /**
     * Initialize session with security settings
     */
    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');

            session_name($this->config['auth']['session_name'] ?? 'fm_session');
            session_start();
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        // Check if login is required
        $requireLogin = $this->config['auth']['require_login'] ?? true;

        // If login is not required, auto-authenticate with default user
        if (!$requireLogin) {
            $this->autoAuthenticate();
            return true;
        }

        // Check session authentication
        $sessionAuth = isset($_SESSION['fm_authenticated'])
            && $_SESSION['fm_authenticated'] === true
            && isset($_SESSION['fm_username']);

        if ($sessionAuth) {
            return true;
        }

        // Check Remember Me cookie if enabled
        if ($this->config['auth']['remember_me'] ?? false) {
            return $this->checkRememberMeCookie();
        }

        return false;
    }

    /**
     * Auto-authenticate with default user (when login is disabled)
     */
    private function autoAuthenticate(): void
    {
        // Only set if not already authenticated
        if (!isset($_SESSION['fm_authenticated']) || $_SESSION['fm_authenticated'] !== true) {
            $defaultUser = $this->config['auth']['default_user'] ?? 'system';
            $_SESSION['fm_authenticated'] = true;
            $_SESSION['fm_username'] = $defaultUser;
            $_SESSION['fm_login_time'] = time();
            $_SESSION['fm_auto_authenticated'] = true; // Flag to indicate auto-auth
        }
    }

    /**
     * Get current authenticated username
     */
    public function getUsername(): ?string
    {
        return $_SESSION['fm_username'] ?? null;
    }

    /**
     * Check if login is required (from config)
     */
    public function isLoginRequired(): bool
    {
        return $this->config['auth']['require_login'] ?? true;
    }

    /**
     * Attempt to log in user
     */
    public function login(string $username, string $password, bool $rememberMe = false): bool
    {
        // Check rate limiting
        if ($this->isRateLimited()) {
            return false;
        }

        // Get users from config
        $users = $this->config['auth']['users'] ?? [];

        if (!isset($users[$username])) {
            $this->recordFailedAttempt();
            return false;
        }

        $user = new User($username, $users[$username]);

        if (!$user->verifyPassword($password)) {
            $this->recordFailedAttempt();
            return false;
        }

        // Successful login
        $this->clearLoginAttempts();
        $_SESSION['fm_authenticated'] = true;
        $_SESSION['fm_username'] = $username;
        $_SESSION['fm_login_time'] = time();

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Create Remember Me cookie if requested and enabled
        if ($rememberMe && ($this->config['auth']['remember_me'] ?? false)) {
            $this->createRememberMeCookie($username);
        }

        return true;
    }

    /**
     * Log out current user
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Clear Remember Me cookie
        $this->clearRememberMeCookie();

        session_destroy();
    }

    /**
     * Check if login attempts are rate limited
     */
    private function isRateLimited(): bool
    {
        $maxAttempts = $this->config['security']['max_login_attempts'] ?? 5;
        $cooldown = $this->config['security']['login_cooldown'] ?? 300;

        $attempts = $_SESSION[self::LOGIN_ATTEMPTS_KEY] ?? 0;
        $lastAttempt = $_SESSION[self::LAST_ATTEMPT_KEY] ?? 0;

        if ($attempts >= $maxAttempts) {
            $timeSinceLastAttempt = time() - $lastAttempt;
            if ($timeSinceLastAttempt < $cooldown) {
                return true;
            }
            // Cooldown expired, reset attempts
            $this->clearLoginAttempts();
        }

        return false;
    }

    /**
     * Record a failed login attempt
     */
    private function recordFailedAttempt(): void
    {
        $_SESSION[self::LOGIN_ATTEMPTS_KEY] = ($_SESSION[self::LOGIN_ATTEMPTS_KEY] ?? 0) + 1;
        $_SESSION[self::LAST_ATTEMPT_KEY] = time();
    }

    /**
     * Clear login attempt records
     */
    private function clearLoginAttempts(): void
    {
        unset($_SESSION[self::LOGIN_ATTEMPTS_KEY], $_SESSION[self::LAST_ATTEMPT_KEY]);
    }

    /**
     * Get remaining cooldown time in seconds
     */
    public function getRemainingCooldown(): int
    {
        $maxAttempts = $this->config['security']['max_login_attempts'] ?? 5;
        $cooldown = $this->config['security']['login_cooldown'] ?? 300;
        $attempts = $_SESSION[self::LOGIN_ATTEMPTS_KEY] ?? 0;
        $lastAttempt = $_SESSION[self::LAST_ATTEMPT_KEY] ?? 0;

        // Only return cooldown if actually rate-limited
        if ($attempts < $maxAttempts) {
            return 0;
        }

        $elapsed = time() - $lastAttempt;

        return max(0, $cooldown - $elapsed);
    }

    /**
     * Create Remember Me cookie with secure token
     */
    private function createRememberMeCookie(string $username): void
    {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $this->storeRememberToken($username, $tokenHash);

        // Create cookie with token (not hash)
        $duration = $this->config['auth']['remember_duration'] ?? 1800;
        $expires = time() + $duration;

        setcookie(
            self::REMEMBER_COOKIE_NAME,
            $token,
            $expires,
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true
        );
    }

    /**
     * Check if Remember Me cookie is valid and auto-login
     */
    private function checkRememberMeCookie(): bool
    {
        // Check if cookie exists
        if (!isset($_COOKIE[self::REMEMBER_COOKIE_NAME])) {
            return false;
        }

        $token = $_COOKIE[self::REMEMBER_COOKIE_NAME];
        $tokenHash = hash('sha256', $token);

        $storedData = $this->getStoredRememberToken();

        if (!$storedData || !isset($storedData['hash'], $storedData['username'], $storedData['created'])) {
            $this->clearRememberMeCookie();
            return false;
        }

        // Verify token matches
        if (!hash_equals($storedData['hash'], $tokenHash)) {
            $this->clearRememberMeCookie();
            return false;
        }

        // Check if token has expired
        $duration = $this->config['auth']['remember_duration'] ?? 1800;
        if (time() - $storedData['created'] > $duration) {
            $this->clearRememberMeCookie();
            return false;
        }

        // Valid token - auto-login user
        $_SESSION['fm_authenticated'] = true;
        $_SESSION['fm_username'] = $storedData['username'];
        $_SESSION['fm_login_time'] = time();
        $_SESSION['fm_auto_authenticated'] = true;

        return true;
    }

    /**
     * Clear Remember Me cookie
     */
    private function clearRememberMeCookie(): void
    {
        $this->deleteStoredRememberToken();

        // Clear cookie
        if (isset($_COOKIE[self::REMEMBER_COOKIE_NAME])) {
            setcookie(
                self::REMEMBER_COOKIE_NAME,
                '',
                time() - 3600,
                '/',
                '',
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                true
            );
        }
    }

    /**
     * Get the file path for persistent remember-me token storage
     */
    private function getRememberTokenFilePath(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fm_remember_tokens.json';
    }

    /**
     * Read all remember-me tokens from the JSON file
     */
    private function readTokenStore(): array
    {
        $path = $this->getRememberTokenFilePath();
        if (!file_exists($path)) {
            return [];
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Write remember-me tokens to the JSON file with exclusive lock
     */
    private function writeTokenStore(array $data): void
    {
        $path = $this->getRememberTokenFilePath();
        @file_put_contents($path, json_encode($data), LOCK_EX);
        @chmod($path, 0600);
    }

    /**
     * Store a new remember-me token for the given username
     */
    private function storeRememberToken(string $username, string $tokenHash): void
    {
        $store = $this->readTokenStore();
        $store[$username] = [
            'hash' => $tokenHash,
            'username' => $username,
            'created' => time(),
        ];
        $this->writeTokenStore($store);
    }

    /**
     * Retrieve stored token by username or by matching the cookie token
     */
    private function getStoredRememberToken(): ?array
    {
        $store = $this->readTokenStore();
        $username = $_SESSION['fm_username'] ?? null;
        if ($username && isset($store[$username])) {
            return $store[$username];
        }
        foreach ($store as $data) {
            if (isset($data['hash'])) {
                $token = $_COOKIE[self::REMEMBER_COOKIE_NAME] ?? '';
                if ($token !== '' && hash_equals($data['hash'], hash('sha256', $token))) {
                    return $data;
                }
            }
        }
        return null;
    }

    /**
     * Remove the remember-me token for the current user
     */
    private function deleteStoredRememberToken(): void
    {
        $store = $this->readTokenStore();
        $username = $_SESSION['fm_username'] ?? null;
        if ($username) {
            unset($store[$username]);
        }
        $this->writeTokenStore($store);
    }
}
