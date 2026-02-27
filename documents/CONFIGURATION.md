# Configuration Reference

Complete reference for all `config.php` options.

---

## Authentication (`auth`)

| Option              | Type     | Default              | Description                                                                                  |
| ------------------- | -------- | -------------------- | -------------------------------------------------------------------------------------------- |
| `require_login`     | `bool`   | `true`               | Set `false` to bypass login (useful when integrating into an app that handles its own auth)  |
| `default_user`      | `string` | `'system'`           | Username used when `require_login` is `false`                                                |
| `users`             | `array`  | `['admin' => '...']` | Associative array of `username => password_hash`. Default credentials: `admin` / `admin@123` |
| `session_name`      | `string` | `'fm_session'`       | PHP session cookie name                                                                      |
| `remember_me`       | `bool`   | `true`               | Show "Remember Me" checkbox on login form                                                    |
| `remember_duration` | `int`    | `1800`               | Remember Me cookie duration in seconds (default: 30 minutes)                                 |

### Generating Password Hashes

```php
// Generate a new password hash
echo password_hash('your-password', PASSWORD_ARGON2ID);
```

### Adding Multiple Users

```php
'users' => [
    'admin'  => password_hash('admin-pass', PASSWORD_ARGON2ID),
    'editor' => password_hash('editor-pass', PASSWORD_ARGON2ID),
    'viewer' => password_hash('viewer-pass', PASSWORD_ARGON2ID),
],
```

---

## File Manager Settings (`fm`)

| Option            | Type      | Default                   | Description                                                                              |
| ----------------- | --------- | ------------------------- | ---------------------------------------------------------------------------------------- |
| `dynamic_folder`  | `bool`    | `false`                   | Enable dynamic folder mode where folder ID comes from URL                                |
| `base_path`       | `string`  | `'/var/www/html/uploads'` | Base directory for dynamic folder mode                                                   |
| `folder_param`    | `string`  | `'folder'`                | URL parameter name for folder ID                                                         |
| `default_folder`  | `?string` | `null`                    | Default folder when no ID provided (`null` = show error)                                 |
| `root_path`       | `string`  | `__DIR__`                 | Root directory in static mode (when `dynamic_folder` is `false`)                         |
| `root_url`        | `string`  | `''`                      | Base URL for assets (for CDN/external hosting)                                           |
| `assets_path`     | `string`  | `''`                      | URL path where package assets are accessible                                             |
| `base_url`        | `string`  | `'index.php'`             | URL path for form actions and redirects                                                  |
| `title`           | `string`  | `'File Manager'`          | Application title shown in browser and header                                            |
| `language`        | `string`  | `'en'`                    | Language code for HTML `lang` attribute                                                  |
| `show_hidden`     | `bool`    | `true`                    | Show hidden files (files starting with `.`)                                              |
| `datetime_format` | `string`  | `'Y-m-d H:i:s'`           | PHP [date format](https://www.php.net/manual/en/datetime.format.php) for file timestamps |
| `show_footer`     | `bool`    | `true`                    | Show the page footer. Set `false` to hide it (useful for embedded/iframe use)            |
| `columns`         | `array`   | See below                 | Control which columns are visible in the file table — see Column Visibility section      |

### Column Visibility (`fm.columns`)

Controls which columns are **displayed** in the file table. This is purely cosmetic — hiding a column does not restrict any operation.

| Option        | Type   | Default | Description                               |
| ------------- | ------ | ------- | ----------------------------------------- |
| `size`        | `bool` | `true`  | File/folder size column                   |
| `owner`       | `bool` | `true`  | File owner (Unix user) column             |
| `modified`    | `bool` | `true`  | Last modified date/time column            |
| `permissions` | `bool` | `true`  | Unix permission string (e.g. `rwxr-xr-x`) |

> [!IMPORTANT]
> **`fm.columns` vs `permissions` — these are independent settings, do not confuse them.**
>
> | Goal | Use |
> |------|-----|
> | Hide the permissions *column* | `fm.columns.permissions = false` |
> | Disallow the chmod *operation* (hide the button) | Remove `'permissions'` from the role's action list |
>
> Hiding `fm.columns.permissions` does **not** remove the chmod button, and does **not** break the Change Permissions modal — the modal always reads the current permission value regardless of column visibility.

```php
'columns' => [
    'size'        => true,
    'owner'       => true,
    'modified'    => true,
    'permissions' => false,  // Hides the column display only
],
```

### Static Mode (Default)

```php
'fm' => [
    'dynamic_folder' => false,
    'root_path' => '/path/to/files',
],
```

### Dynamic Folder Mode

```php
'fm' => [
    'dynamic_folder' => true,
    'base_path' => '/var/www/uploads/tasks',
    'folder_param' => 'folder',
    // URL: /file-manager/123 → manages /var/www/uploads/tasks/123
],
```

---

## Upload Settings (`upload`)

| Option               | Type    | Default           | Description                                                                           |
| -------------------- | ------- | ----------------- | ------------------------------------------------------------------------------------- |
| `max_file_size`      | `int`   | `52428800` (50MB) | Maximum upload file size in bytes                                                     |
| `allowed_extensions` | `array` | `['*']`           | Allowed file extensions. Use `['*']` for all, or specify like `['jpg', 'png', 'pdf']` |
| `chunk_size`         | `int`   | `1048576` (1MB)   | Chunk size for Dropzone.js chunked uploads                                            |

> [!NOTE]
> You may also need to update `php.ini` settings: `upload_max_filesize`, `post_max_size`, and `max_execution_time`.

---

## Security (`security`)

| Option               | Type   | Default | Description                                                         |
| -------------------- | ------ | ------- | ------------------------------------------------------------------- |
| `csrf_protection`    | `bool` | `true`  | Enable CSRF token validation. Disable for API integrations          |
| `max_login_attempts` | `int`  | `50`    | Maximum failed login attempts before cooldown                       |
| `login_cooldown`     | `int`  | `300`   | Lockout duration in seconds after max attempts (default: 5 minutes) |

---

## Permissions (`permissions`)

Role-based access control for all file manager actions.

| Option         | Type     | Default   | Description                          |
| -------------- | -------- | --------- | ------------------------------------ |
| `default_role` | `string` | `'admin'` | Role applied to all users by default |
| `roles`        | `array`  | See below | Map of role names to allowed actions |

### Available Actions

| Action        | Description                                          |
| ------------- | ---------------------------------------------------- |
| `upload`      | Upload files                                         |
| `download`    | Download files (single and multiple)                 |
| `delete`      | Delete files and folders                             |
| `rename`      | Rename files and folders                             |
| `new_folder`  | Create new folders                                   |
| `copy`        | Copy files and folders                               |
| `move`        | Move/cut files and folders (including drag-and-drop) |
| `view`        | Preview files (images, text, code)                   |
| `view_pdf`    | View PDF files inline in browser                     |
| `extract`     | Extract ZIP archives                                 |
| `zip`         | Compress files into ZIP archives                     |
| `permissions` | Change file/folder permissions (chmod)               |

### Default Roles

```php
'permissions' => [
    'default_role' => 'admin',
    'roles' => [
        'admin'  => ['*'],  // All actions (wildcard)
        'editor' => ['upload', 'download', 'delete', 'rename', 'new_folder',
                     'copy', 'move', 'view', 'view_pdf', 'extract', 'zip'],
        'viewer' => ['view', 'view_pdf', 'download'],
    ],
],
```

### Creating Custom Roles

```php
'roles' => [
    'admin' => ['*'],
    'uploader' => ['upload', 'view', 'download'],
    'readonly' => ['view', 'download'],
],
```

### Setting Roles in Framework Integration

When integrating with a framework (e.g., CodeIgniter 4), you can set the role dynamically based on the user:

```php
$fm = new FileManagerService($config);

// Set role based on your app's user role
$userRole = auth()->user()->role; // e.g., 'admin', 'editor', 'viewer'
$fm->setPermissionRole($userRole);

$fm->render();
```

> [!IMPORTANT]
> Permissions are enforced on **both** the frontend (buttons/menus are hidden) and the backend (actions are blocked with an error message). Even if a user manipulates the URL, they cannot perform unauthorized actions.

---

## System (`system`)

| Option            | Type     | Default   | Description                                                   |
| ----------------- | -------- | --------- | ------------------------------------------------------------- |
| `timezone`        | `string` | `'UTC'`   | PHP timezone for date/time display                            |
| `error_reporting` | `int`    | `E_ALL`   | PHP error reporting level                                     |
| `display_errors`  | `bool`   | `false`   | Display PHP errors to users (set `true` only for development) |
| `log_errors`      | `bool`   | `true`    | Log errors to PHP error log                                   |
| `charset`         | `string` | `'UTF-8'` | Default character encoding                                    |

---

## Excluded Items (`exclude_items`)

Array of file/directory names that won't be shown in the file manager.

```php
'exclude_items' => [
    '.git',
    '.gitignore',
    '.htaccess',
    'config.php',
    'vendor',
    'node_modules',
],
```

---

## Full Example

```php
<?php
return [
    'auth' => [
        'require_login' => true,
        'users' => [
            'admin' => '$argon2id$v=19$m=65536,t=4,p=1$...',
        ],
        'remember_me' => true,
        'remember_duration' => 3600,
    ],
    'fm' => [
        'root_path'       => '/var/www/html/uploads',
        'title'           => 'My File Manager',
        'show_hidden'     => false,
        'show_footer'     => true,
        'columns' => [
            'size'        => true,
            'owner'       => true,
            'modified'    => true,
            'permissions' => true,
        ],
    ],
    'upload' => [
        'max_file_size'      => 100 * 1024 * 1024, // 100MB
        'allowed_extensions' => ['jpg', 'png', 'gif', 'pdf', 'docx', 'zip'],
    ],
    'security' => [
        'csrf_protection' => true,
    ],
    'permissions' => [
        'default_role' => 'editor',
        'roles' => [
            'admin'  => ['*'],
            'editor' => ['upload', 'download', 'delete', 'rename', 'new_folder',
                         'copy', 'move', 'view', 'view_pdf', 'extract', 'zip'],
            'viewer' => ['view', 'view_pdf', 'download'],
        ],
    ],
    'system' => [
        'timezone'       => 'America/Denver',
        'display_errors' => false,
    ],
    'exclude_items' => [
        '.git', '.env', 'vendor', 'node_modules',
    ],
];
```
