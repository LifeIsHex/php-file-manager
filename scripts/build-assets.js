/*
 * Copyright (C) 2026 Mahdi Hezaveh, MIT License.
 *
 * Author: Mahdi Hezaveh <mahdi.hezaveh@icloud.com> | Username: hezaveh
 * Filename: build-assets.js
 *
 * Last Modified: Sat, 28 Feb 2026 - 17:16:44 MST (-0700)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

#!/usr/bin/env node
/**
 * build-assets.js
 *
 * Copies compiled/minified dist files from node_modules/ into assets/.
 * Run with: npm run build
 *
 * After updating a package version in package.json and running `npm install`,
 * run this script to refresh the committed assets.
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const NM = path.join(ROOT, 'node_modules');
const ASSETS = path.join(ROOT, 'assets');

// ─── helpers ────────────────────────────────────────────────────────────────

function ensureDir(dir) {
    fs.mkdirSync(dir, {recursive: true});
}

function copy(src, dest) {
    ensureDir(path.dirname(dest));
    fs.copyFileSync(src, dest);
    console.log(`  ✓  ${path.relative(ROOT, dest)}`);
}

function copyDir(src, dest) {
    ensureDir(dest);
    for (const entry of fs.readdirSync(src, {withFileTypes: true})) {
        const s = path.join(src, entry.name);
        const d = path.join(dest, entry.name);
        if (entry.isDirectory()) {
            copyDir(s, d);
        } else {
            copy(s, d);
        }
    }
}

function packageVersion(name) {
    const pkg = JSON.parse(fs.readFileSync(path.join(NM, name, 'package.json'), 'utf8'));
    return pkg.version;
}

// ─── build steps ────────────────────────────────────────────────────────────

const steps = [
    // Bulma
    {
        label: 'Bulma CSS',
        run() {
            const ver = packageVersion('bulma');
            const dest = path.join(ASSETS, 'bulma', 'css');
            ensureDir(dest);
            copy(path.join(NM, 'bulma', 'css', 'bulma.min.css'), path.join(dest, 'bulma.min.css'));
            console.log(`    version: ${ver}`);
        },
    },

    // Font Awesome
    {
        label: 'Font Awesome',
        run() {
            const ver = packageVersion('@fortawesome/fontawesome-free');
            const src = path.join(NM, '@fortawesome', 'fontawesome-free');
            const dest = path.join(ASSETS, 'fonts', 'font-awesome');
            // css
            copy(path.join(src, 'css', 'all.min.css'), path.join(dest, 'css', 'all.min.css'));
            // webfonts
            copyDir(path.join(src, 'webfonts'), path.join(dest, 'webfonts'));
            console.log(`    version: ${ver}`);
        },
    },

    // Dropzone
    {
        label: 'Dropzone',
        run() {
            const ver = packageVersion('dropzone');
            const dest = path.join(ASSETS, 'libs', 'dropzone');
            copy(
                path.join(NM, 'dropzone', 'dist', 'min', 'dropzone.min.css'),
                path.join(dest, 'dropzone.min.css')
            );
            copy(
                path.join(NM, 'dropzone', 'dist', 'min', 'dropzone.min.js'),
                path.join(dest, 'dropzone.min.js')
            );
            console.log(`    version: ${ver}`);
        },
    },

    // SortableJS
    {
        label: 'SortableJS',
        run() {
            const ver = packageVersion('sortablejs');
            const dest = path.join(ASSETS, 'libs', 'sortable');
            copy(
                path.join(NM, 'sortablejs', 'Sortable.min.js'),
                path.join(dest, 'Sortable.min.js')
            );
            console.log(`    version: ${ver}`);
        },
    },

    // Bulma Responsive Tables
    {
        label: 'Bulma Responsive Tables',
        run() {
            const ver = packageVersion('bulma-responsive-tables');
            const dest = path.join(ASSETS, 'libs', 'bulma-responsive-tables');
            copy(
                path.join(NM, 'bulma-responsive-tables', 'css', 'main.min.css'),
                path.join(dest, 'main.min.css')
            );
            console.log(`    version: ${ver}`);
        },
    },
];

// ─── run ────────────────────────────────────────────────────────────────────

console.log('\n📦 Building frontend assets...\n');

let errors = 0;
for (const step of steps) {
    console.log(`→ ${step.label}`);
    try {
        step.run();
    } catch (e) {
        console.error(`  ✗  ${e.message}`);
        errors++;
    }
}

if (errors > 0) {
    console.error(`\n⚠️  ${errors} step(s) failed. Run \`npm install\` first.\n`);
    process.exit(1);
} else {
    console.log('\n✅ All assets built successfully.\n');
}
