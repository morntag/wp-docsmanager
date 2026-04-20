# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A standalone WordPress plugin for documenting other WordPress plugins. Originally extracted from `morntag/mcc-baspo` as a Composer library, then converted to a self-contained plugin in v0.3.0. Self-bootstraps on `plugins_loaded`; configuration lives in a single WP option (`docsmanager_settings`) edited via the **Documentation → Settings** admin page.

The repo is open source and ships as a downloadable ZIP from GitHub Releases. Sites auto-update via [yahnis-elsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) against the public Releases API (no GH token required).

## Build Commands

```bash
composer install                # PHP deps (Parsedown, Symfony YAML)
npm install                     # JS deps (Tiptap editor)
npm run build                   # esbuild: assets/src/editor.js → assets/js/editor.bundle.js
```

The compiled `editor.bundle.js` is tracked in git so users get a ready-to-install ZIP without running a JS toolchain.

## Code Quality

```bash
composer phpcs                    # Lint all PHP (WordPress coding standards)
composer phpcbf                   # Auto-fix coding standard violations
composer phpstan                  # Static analysis (level 6)
composer test                     # PHPUnit unit tests (no WP bootstrap needed)
./vendor/bin/phpcs <file>         # Lint a specific file
./vendor/bin/phpcbf <file>        # Auto-fix a specific file
npx @biomejs/biome check assets/src/  # Lint/format JS source files
```

- PHPCS rules: `phpcs.xml` — WordPress-Extra + Core + Docs, tabs, `WordPress.Files.FileName` excluded, `tests/` excluded
- PHPStan config: `phpstan.neon` — level 6, excludes `views/` (template files use injected scope variables)
- PHPUnit config: `phpunit.xml` — unit tests in `tests/Unit/`, WP functions stubbed in `tests/bootstrap.php`
- Biome config: `biome.json` — JS formatter/import organizer (tabs, semicolons, single quotes), ignores `editor.bundle.js`
- `DocsManager.php` has 5 intentional direct-DB-call warnings (bypasses WP for memory reasons) — these are expected

## Git Hooks & Conventions

[Lefthook](https://github.com/evilmartians/lefthook) enforces quality on every commit/push:

- **commit-msg** — [commitlint](https://commitlint.js.org/) validates conventional commits. Allowed types: `feat`, `fix`, `docs`, `chore`, `ci`, `refactor`, `style`, `agent`, `wip`. Subject must be lowercase.
- **pre-commit** — PHPCBF auto-fix + PHPCS on staged PHP files; Biome auto-fix + check on staged JS files
- **pre-push** — Branch naming: `main`, `dev`, `feature/*`, `bugfix/*`, `hotfix/*` (lowercase, hyphens only)

After cloning, run `npm install` — lefthook installs automatically.

## Release Workflow

Automated via [release-it](https://github.com/release-it/release-it) + GitHub Actions (`.github/workflows/release.yml`):

1. Push to `main` triggers CI
2. **Gate**: PHPUnit, PHPStan, PHPCS must all pass
3. **Release**: release-it analyzes conventional commits, determines semver bump, patches the `Version:` line in `wp-docsmanager.php`, generates `CHANGELOG.md`, creates a git tag and GitHub Release
4. **Package**: CI rsyncs the repo into a clean staging dir (excluding dev tooling), zips it as `wp-docsmanager-vX.Y.Z.zip`, and uploads it as a release asset
5. Release commits (`chore(release): v*`) and `[skip ci]` markers are auto-skipped

Only `feat` and `fix` commits trigger a release. `chore`, `docs`, `style`, `wip` are no-ops.

Manual dry-run: trigger the workflow via GitHub Actions UI with the dry-run checkbox, or locally with `npm run release:dry`.

Sites receive updates via [yahnis-elsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) (PUC v5), wired in `wp-docsmanager.php` against the public GitHub repo with `enableReleaseAssets()`. Exclusions for the shipped ZIP live in `.zipexclude` (rsync-style patterns).

## Architecture

### Bootstrap Flow

1. WP loads `wp-docsmanager.php` (plugin header file at repo root)
2. `wp-docsmanager.php` requires `vendor/autoload.php`, registers activation/uninstall hooks, hooks `plugins_loaded:20` to call `DocsManager::instance()`
3. `Module::__construct()` (parent of `DocsManager`) calls `register_hooks()`, `register_filters()`, `init()`
4. `DocsManager::init()` reads the `docsmanager_settings` option via `SettingsRepository`, resolves scan paths via `PathResolver` (which applies the `morntag_docs_modules_dir` / `morntag_docs_docs_dir` filter overrides), and instantiates the services with the resolved paths
5. `SettingsPage` instantiated in `init()` binds itself to `admin_menu` and `admin_init`

There is **no** `boot()` API anymore. Host plugins do not inject config — settings are owned by the plugin's own UI. The two filter escape hatches let code override paths if needed.

### Class Hierarchy

```
Module (abstract — singleton pattern, declarative $hooks/$filters arrays)
  └─ DocsManager (main orchestrator)
       ├─ Models\Documentation — CPT & taxonomy registration
       └─ Services\
            ├─ FileScanner       — scans modules_dir/*/README.md and docs_dir/**/*.md, transient-cached 1h
            ├─ MarkdownParser    — Parsedown Extra wrapper, frontmatter via Symfony YAML, TOC generation
            ├─ SearchService     — unified full-text search across posts + scanned files
            ├─ SettingsRepository — read/write the `docsmanager_settings` option with defaults
            ├─ SubpathSanitizer  — static sanitiser, rejects `..` / leading `/` / `\`
            ├─ PathResolver      — composes `WP_PLUGIN_DIR/<slug>/<subpath>`, applies override filters, derives allowed_roots
            ├─ SettingsPage      — admin submenu, form, warnings, register_setting wiring
            └─ RescanHandler     — `admin_post_morntag_docs_rescan` action, deletes scan transients

Activation              — static activate(); grants the three docsmanager_*_docs caps to administrator on plugin activation
UninstallHandler        — static run(); removes option, transients, caps from all roles. Posts/terms/meta are NOT touched.
uninstall.php           — lowercase WP entry point; guarded by WP_UNINSTALL_PLUGIN; requires autoload then calls UninstallHandler::run()
wp-docsmanager.php      — plugin header file, registers hooks, self-bootstraps
```

### Namespace & Autoloading

PSR-4: `Morntag\WpDocsManager\` maps to the repo root. Files directly match their namespace path. `composer.json` `type` is `wordpress-plugin` and has no `version` field (the Git tag is the source of truth).

### Settings

Stored as a single autoloaded option `docsmanager_settings`:

```php
[
    'plugin_slug'           => '',          // folder name under WP_PLUGIN_DIR (e.g. 'mcc-baspo')
    'modules_scan_enabled'  => false,
    'modules_subpath'       => 'includes/Modules',
    'docs_scan_enabled'     => false,
    'docs_subpath'          => '.docs',
]
```

`SettingsRepository::save()` runs subpaths through `SubpathSanitizer::sanitize()`. Invalid input (`..`, leading `/`, backslash) short-circuits the save and emits `add_settings_error()` — the prior stored value is left intact.

`PathResolver` composes absolute paths and applies `morntag_docs_modules_dir` / `morntag_docs_docs_dir` filters. When a filter is registered, `SettingsPage` shows the field as read-only with an "Overridden by filter" notice.

`allowed_roots` (used by the viewer's `realpath()` check) is auto-derived from the enabled scans only — there is no separate setting.

### Frozen mcc_* Identifiers

These are **not configurable** — changing them would orphan existing data in installations originating from `mcc-baspo`:

- CPT: `mcc_documentation`, Taxonomy: `mcc_doc_category`
- Menu slug: `mcc-documentation` (settings submenu: `mcc-documentation-settings`)
- Post meta: `_mcc_doc_frontmatter`, `_mcc_doc_type`, `_mcc_doc_source_path`, `_mcc_doc_order`, `_mcc_doc_readonly`
- CPT `capability_type`: `['mcc_doc', 'mcc_docs']` (powers `map_meta_cap`; not used by the plugin's own UI guards)
- AJAX actions / nonces: `morntag_docs_search`, `morntag_docs_list`, `morntag_docs_save`, `morntag_docs_delete_{id}`, `morntag_docs_nonce`
- Filter hooks: `morntag_docs_user_can`, `morntag_docs_modules_dir`, `morntag_docs_docs_dir`

The **UI-guard capability strings** (granted to roles via `add_cap`) were renamed in the standalone conversion: `mcc_access_docs` → `docsmanager_access_docs`, `mcc_edit_docs` → `docsmanager_edit_docs`, `mcc_delete_docs` → `docsmanager_delete_docs`. CPT/taxonomy/meta names stayed frozen so data is preserved.

### Admin UI

Three views in `views/`:
- `admin-page.view.php` — sidebar navigation (search, collapsible sections for module/dev/custom docs), empty-state notice when nothing configured, **Rescan now** button
- `viewer.view.php` — rendered Markdown with auto-generated TOC; `realpath()` boundary check against `get_allowed_roots()`
- `editor.view.php` — Tiptap WYSIWYG form (Markdown in/out)

JS in `assets/js/admin.js` handles search (debounced AJAX), sidebar collapse (localStorage), print, TOC scroll.

### Tiptap Editor

Source: `assets/src/editor.js` with custom extensions in `assets/src/extensions/` (iframe, image, video) and modals in `assets/src/components/` (doc-picker, media). Built via esbuild into a single IIFE bundle. The bundle is committed.

### Activation & Uninstall

- **Activation** (`Activation::activate`): grants `docsmanager_access_docs`, `docsmanager_edit_docs`, `docsmanager_delete_docs` to the `administrator` role. Idempotent — safe to re-activate.
- **Deactivation**: no-op. Settings, caps, posts all preserved.
- **Uninstall** (`uninstall.php` → `UninstallHandler::run`): removes the `docsmanager_settings` option, both scanner transients, and the three UI-guard caps from every role. `mcc_documentation` posts, `mcc_doc_category` terms, and `_mcc_doc_*` post meta are deliberately preserved (data may have originated from a previously-installed host plugin).

### Multisite

**Main site only.** On a multisite network, the bootstrap in `wp-docsmanager.php` checks `is_multisite() && ! is_main_site()` and returns early — `DocsManager::instance()` is never called on subsites, so no CPT, no admin menu, no AJAX handlers, no settings UI. Documentation is treated as network-wide meta that lives on the main site; subsite admins do not see the plugin at all.

### Security Model

- Capability checks via `DocsManager::user_can($cap)` which runs the `morntag_docs_user_can` filter, then falls back to `current_user_can('manage_options')`
- File viewer validates `?path=` URL parameter against `get_allowed_roots()` using `realpath()`
- Nonces: `morntag_docs_save`, `morntag_docs_delete_{id}`, `morntag_docs_nonce` (AJAX), `morntag_docs_rescan` (admin-post)
- `wp_kses_post()` on save; extended allowed tags for iframe/video/source via `wp_kses_allowed_html` filter
