# Integration Guide

This guide explains how to integrate the PHP File Manager package into your project for both development and production environments.

## Prerequisites

- PHP 8.3 or higher
- Composer

---

## Development Setup (with Docker)

### Directory Structure

```
your-project/
├── www/
│   ├── your-app/          # Your main application (e.g., CodeIgniter 4)
│   └── file-manager/      # This package (cloned/copied here)
├── docker/
└── docker-compose.yml
```

### 1. Mount the Package

In your `docker-compose.yml`:

```yaml
services:
  webservice:
    volumes:
      - ./www/your-app:/var/www/html
      - ./www/file-manager:/var/www/file-manager  # Mount package
```

### 2. Update Composer Configuration

In your **application's** `composer.json`:

```json
{
    "minimum-stability": "dev",
    "prefer-stable": true,
    "repositories": [
        {
            "type": "path",
            "url": "../file-manager",
            "options": {
                "symlink": false
            }
        }
    ],
    "require": {
        "lifeishex/php-file-manager": "@dev"
    }
}
```

> **Important:** Use relative path `../file-manager` - this works on both host and Docker!

### 3. Install Dependencies

```bash
# Restart Docker to apply volume mounts
docker-compose down && docker-compose up -d

# Install via Docker
docker exec -it your-container-name bash
cd /var/www/html
composer install
```

**Or install on host first:**

```bash
cd www/your-app
composer install
# Docker will use the mounted vendor folder
```

---

## Production Setup

For production, you have three options:

### Option 1: Publish to Private Repository (Recommended)

1. **Push to GitHub/GitLab:**
   ```bash
   cd file-manager
   git init
   git add .
   git commit -m "Initial commit"
   git remote add origin https://github.com/yourorg/php-file-manager.git
   git push -u origin main
   ```

2. **Update composer.json:**
   ```json
   {
       "repositories": [
           {
               "type": "vcs",
               "url": "https://github.com/LifeIsHex/php-file-manager.git"
           }
       ],
       "require": {
           "lifeishex/php-file-manager": "dev-main"
       }
   }
   ```

3. **For private repos, add authentication:**
   ```bash
   composer config --global github-oauth.github.com YOUR_GITHUB_TOKEN
   ```

### Option 2: Package as ZIP

1.  **Create a distributable package:**
    ```bash
    cd file-manager
    zip -r php-file-manager.zip . -x "*.git*"
    ```

2.  **Host on a web server and use:**
    ```json
    {
        "repositories": [
            {
                "type": "package",
                "package": {
                    "name": "lifeishex/php-file-manager",
                    "version": "1.0.0",
                    "dist": {
                        "url": "https://yourserver.com/packages/php-file-manager.zip",
                        "type": "zip"
                    },
                    "autoload": {
                        "psr-4": {
                            "FileManager\\": "src/"
                        }
                    }
                }
            }
        ],
        "require": {
            "lifeishex/php-file-manager": "1.0.0"
        }
    }
    ```

### Option 3: Copy to Vendor (Not Recommended)

For quick deployments without Composer:

```bash
# In production
mkdir -p vendor/lifeishex
cp -r /path/to/file-manager vendor/lifeishex/php-file-manager
```

Then manually include Composer's autoloader in your code:
```php
require_once __DIR__ . '/vendor/autoload.php';
```

---

## Troubleshooting

### "Package not found" Error

- ✅ Verify the path exists: `ls ../file-manager/composer.json`
- ✅ Check package name matches in both composer.json files
- ✅ Clear composer cache: `composer clear-cache`
- ✅ Delete and reinstall: `rm -rf vendor composer.lock && composer install`

### "Could not delete vendor" Error

This is a permissions issue in Docker:

```bash
# Inside container
chown -R www-data:www-data /var/www/html
# Or
chown -R $(whoami):$(whoami) /var/www/html
```

### Different paths in Docker vs Host

Always use **relative paths** (`../file-manager`) instead of absolute paths. This ensures compatibility between host and container environments.

---

## Version Management

### Development
```json
"require": {
    "lifeishex/php-file-manager": "@dev"
}
```

### Production with Git Tags
```bash
# Tag a release
git tag v1.0.0
git push --tags
```

```json
"require": {
    "lifeishex/php-file-manager": "^1.0"
}
```

---

## Dynamic Folder Mode

The file manager supports **dynamic folder access** where each user/task gets their own isolated folder via URL.

### Enable Dynamic Mode

```php
// config.php
'fm' => [
    'dynamic_folder' => true,
    'base_path' => '/var/www/html/uploads/tasks',
    'folder_param' => 'folder',
    'default_folder' => null, // null = require folder, '' = allow base path
],
```

### URL Patterns

- **Path-based**: `/file-manager/120` → Manages `/base_path/120/`
- **Query-based**: `/file-manager?folder=120` → Same result

### Security Features

✅ Folder IDs are validated (alphanumeric, dashes, underscores only)
✅ Path traversal attacks are blocked
✅ Users are sandboxed to their folder
✅ Folders are auto-created if they don't exist

---

## CodeIgniter 4 Integration

### 1. Install the Package

```bash
composer require lifeishex/php-file-manager:@dev
```

### 2. Copy Assets to Public Folder

The file manager needs its CSS/JS assets to be web-accessible:

```bash
# Copy assets to your CI4 public folder
mkdir -p public/filemanager
cp -r vendor/lifeishex/php-file-manager/assets/* public/filemanager/
```

### 3. Create a Controller

```php
<?php
// app/Controllers/FileManagerController.php

namespace App\Controllers;

use FileManager\Integration\FileManagerService;

class FileManagerController extends BaseController
{
    public function index(?string $folderId = null)
    {
        // Check user authentication (your app's auth)
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login');
        }

        // Optional: Check if user can access this folder
        if ($folderId && !$this->canUserAccess($folderId)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $fm = new FileManagerService([
            'auth' => ['require_login' => false],
            'fm' => [
                'dynamic_folder' => true,
                'base_path' => WRITEPATH . 'uploads/tasks',
                'title' => 'Project Files',
                'assets_path' => '/filemanager',  // Path to assets in public folder
                'base_url' => '/file-manager' . ($folderId ? '/' . $folderId : ''),  // CI4 route URL
            ],
            'security' => ['csrf_protection' => false], // CI4 handles CSRF
        ]);

        if ($folderId) {
            $fm->setFolder($folderId);
        }

        $fm->render();
    }

    private function canUserAccess(string $folderId): bool
    {
        // Your permission logic here
        return true;
    }
}
```

### 4. Add Routes

```php
// app/Config/Routes.php
$routes->get('file-manager', 'FileManagerController::index');
$routes->get('file-manager/(:segment)', 'FileManagerController::index/$1');
$routes->get('file-manager/(:segment)/(:any)', 'FileManagerController::index/$1');
$routes->post('file-manager/(:segment)', 'FileManagerController::index/$1');
$routes->post('file-manager/(:segment)/(:any)', 'FileManagerController::index/$1');
```

---

## FileManagerService API

```php
use FileManager\Integration\FileManagerService;

// Create with config
$fm = new FileManagerService($config);

// Set folder programmatically
$fm->setFolder('task-123');

// Add custom access control
$fm->setAccessValidator(function(string $folderId) use ($userModel) {
    return $userModel->canAccess($folderId);
});

// Get resolved path
$path = $fm->getRootPath();

// Render the file manager
$fm->render();
```

