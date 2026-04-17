# Plan: Standalone Plugin Conversion

> Source PRD: grilled decision set from conversation 2026-04-17 — converts `morntag/wp-docsmanager` from a shared Composer library into a standalone, open-source WordPress plugin with its own settings page and update channel. Replaces bundling into `mcc-baspo`.

## Architectural decisions

Durable decisions that apply across all phases:

- **Plugin identity**: slug `wp-docsmanager`; main file `wp-docsmanager.php` with plugin header; plugin name "WP Docs Manager"; starting version `0.3.0`.
- **Composer**: `type: "wordpress-plugin"`; no `"version"` field (Git tags are source of truth); `symfony/dotenv` removed; `yahnis-elsts/plugin-update-checker` added.
- **Namespace**: `Morntag\WpDocsManager\` (unchanged); `Module` base class retained; `SettingsPage` is a plain service.
- **Admin menu**: top-level `Documentation` (slug `mcc-documentation`) with submenu `Documentation → Settings` (slug `mcc-documentation-settings`).
- **Settings storage**: single WP option `docsmanager_settings` holding an array:
  - `plugin_slug` (string — folder name under `WP_PLUGIN_DIR`)
  - `modules_scan_enabled` (bool)
  - `modules_subpath` (string, default `includes/Modules`)
  - `docs_scan_enabled` (bool)
  - `docs_subpath` (string, default `.docs`)
- **Scan scope**: single-plugin, subpaths must resolve under the selected plugin's directory. `allowed_roots` auto-derived from the two enabled scan paths; not exposed to UI.
- **Capabilities (UI-guard set — renamed)**: `docsmanager_access_docs`, `docsmanager_edit_docs`, `docsmanager_delete_docs`. Granted to `administrator` on activation. Removed on uninstall only. Deactivation is a no-op.
- **Frozen data identifiers (unchanged)**: CPT `mcc_documentation`, taxonomy `mcc_doc_category`, post meta `_mcc_doc_*`, CPT `capability_type` `['mcc_doc', 'mcc_docs']`. No DB migration required.
- **Public API hooks (unchanged names)**: `morntag_docs_user_can`, `morntag_docs_search`, `morntag_docs_list`, nonces `morntag_docs_save` / `morntag_docs_delete_*` / `morntag_docs_nonce`. New path-override filters: `morntag_docs_modules_dir`, `morntag_docs_docs_dir`.
- **Multisite**: per-site activation; no main-site restriction.
- **Update channel**: public GitHub repo `morntag/wp-docsmanager`; branch `main`; `enableReleaseAssets()` with no authentication; ZIP artifact `wp-docsmanager-v${VERSION}.zip` containing a top-level folder `wp-docsmanager/`.
- **Uninstall policy**: remove `docsmanager_settings`, transients, and the three UI-guard caps from all roles. Posts, taxonomy terms, and post meta are left intact (data may belong to a previously-installed host).

---

## Phase 1: Standalone plugin shell

**User stories**:
- As a site admin, I can install `wp-docsmanager` as a standalone WordPress plugin via ZIP upload and activate it.
- As a site admin upgrading from a baspo-bundled install, my existing `mcc_documentation` posts and categories appear intact after activation.
- As an administrator, I receive the three renamed `docsmanager_*_docs` capabilities automatically on activation.

### What to build

Add a plugin-header bootstrap file at the repo root that self-registers on `plugins_loaded`. The plugin must be installable and activatable on a fresh WordPress site. The existing class-based architecture (`DocsManager` + services + `Module` base) is retained; the `boot()` / `$pending_config` injection API is removed because the plugin now bootstraps itself. The UI-guard capability strings are renamed throughout the codebase from `mcc_*_docs` to `docsmanager_*_docs`. An activation hook grants the renamed caps to the `administrator` role. An `uninstall.php` file removes the plugin's option, transients, and cap grants but leaves `mcc_documentation` posts / taxonomy terms / post meta untouched (they may have originated from a previous baspo install). Composer metadata flips from `library` to `wordpress-plugin`, drops the `"version"` field, and removes `symfony/dotenv`. No settings UI yet — `FileScanner` receives empty paths and returns nothing, so the sidebar shows only custom CPT posts.

### Acceptance criteria

- [ ] A built ZIP of the repo installs via `Plugins → Add New → Upload Plugin` on a fresh WP site and activates without PHP errors.
- [ ] Plugin header contains: `Plugin Name: WP Docs Manager`, `Version: 0.3.0`, `Plugin URI`, `Author: morntag.com`, `License: GPL-2.0-or-later`, `Text Domain: wp-docsmanager`.
- [ ] `Documentation` menu appears in wp-admin for administrators.
- [ ] On a site with pre-existing `mcc_documentation` posts and `mcc_doc_category` terms, all of them render in the sidebar and are editable.
- [ ] All `user_can()` call sites inside `DocsManager` use `docsmanager_*_docs` strings; no `mcc_*_docs` strings remain in the UI-guard code paths.
- [ ] After activation, the `administrator` role has all three `docsmanager_*_docs` capabilities (verifiable via `user_can()` or role inspection).
- [ ] `composer.json` has `type: "wordpress-plugin"`, no `"version"` key, no `symfony/dotenv` in requires.
- [ ] `DocsManager::boot()` and `$pending_config` are removed; `DocsManager` is constructed on `plugins_loaded` without external orchestration.
- [ ] `uninstall.php` exists and, when triggered, removes `docsmanager_settings`, clears the scanner transients, and strips the three caps from every role. Posts, terms, and post meta are untouched.
- [ ] Multisite: on a network install, each subsite can independently activate and use the plugin (no `get_current_blog_id() === 1` gate).
- [ ] Existing unit tests still pass; new tests cover the activation cap-grant and uninstall cap-revoke logic.

---

## Phase 2: Settings page & file scanning

**User stories**:
- As a site admin, I can open `Documentation → Settings`, pick an installed plugin from a dropdown, and choose which of its subdirectories to scan for Markdown docs.
- As a site admin, I can independently enable or disable the "module READMEs" scan and the "docs tree" scan.
- As a site admin, I can click "Rescan now" to force a fresh filesystem walk.
- As a developer, I can override the configured paths via the `morntag_docs_modules_dir` and `morntag_docs_docs_dir` filters for programmatic setup.
- As a site admin, if the selected plugin is not active or a subpath is missing, I see a clear warning on the settings screen rather than a silent failure.

### What to build

A new `SettingsPage` service wires up the submenu `Documentation → Settings`, renders a form, handles save via `register_setting`, and validates input. The form fields are: plugin dropdown (populated from `get_plugins()`, stores folder slug only), two enable toggles, two subpath text fields with defaults `includes/Modules` and `.docs`. Subpath validation: reject `..`, reject leading `/` or backslashes, strip trailing slashes, confirm the `realpath()` result stays under the selected plugin's directory. Warnings (non-blocking on save): selected plugin not active, subpath does not exist on disk. `DocsManager::init()` hydrates `FileScanner` by reading the option and computing absolute paths (`WP_PLUGIN_DIR . '/' . $slug . '/' . $subpath`). The `morntag_docs_modules_dir` and `morntag_docs_docs_dir` filters allow code to override the resolved paths (filter runs after option load). `allowed_roots` is auto-derived from whichever scans are enabled and used by the viewer's `realpath()` check. A "Rescan now" button on the Documentation page fires a nonce-guarded action that deletes the scanner transients and redirects back. When no settings are configured (fresh install) or both scans are disabled, the sidebar still renders custom CPT posts but shows an empty-state notice linking to settings for file-based docs.

### Acceptance criteria

- [ ] `Documentation → Settings` submenu exists and is gated by `manage_options`.
- [ ] The plugin dropdown lists all installed plugins (active and inactive) by name, storing the folder slug as the option value.
- [ ] Saving settings with valid inputs updates `docsmanager_settings` and redirects with a success notice.
- [ ] Submitting a subpath containing `..` or `/` is rejected with a validation error and settings are not saved.
- [ ] Submitting a valid subpath that doesn't exist on disk saves successfully and shows a non-blocking warning on the settings screen.
- [ ] Saving with an inactive plugin selected shows a warning on the settings screen but does not block.
- [ ] With module READMEs enabled and a valid path, the sidebar's "Module Docs" section populates; disabling the toggle hides that section on next page load.
- [ ] Same independence holds for the docs-tree scan.
- [ ] A filter callback registered for `morntag_docs_modules_dir` returning a path overrides the stored setting; the settings screen displays the override as read-only with "overridden by filter" text.
- [ ] Clicking "Rescan now" deletes the scanner transients (verifiable via `get_transient()` returning false) and subsequent page load repopulates them.
- [ ] The viewer rejects a `?path=` URL pointing outside the auto-derived `allowed_roots` even when the file exists on disk.
- [ ] Fresh install (no saved settings) renders the Documentation page with an empty-state notice linking to settings; no PHP warnings.
- [ ] Settings-layer tests cover: plugin-slug storage shape, subpath sanitization (`..`, leading slash, backslash, trailing slash), filter override precedence, transient invalidation.

---

## Phase 3: Release packaging & update checker

**User stories**:
- As a plugin maintainer, merging a conventional-commit feature or fix to `main` produces a tagged GitHub release with an installable ZIP asset attached.
- As a site admin with the plugin installed, I receive the standard WordPress "Update available" notice in `Plugins` when a new release is published, and clicking Update installs the new version.
- As a plugin maintainer, I can perform a dry-run release via GitHub Actions `workflow_dispatch` without creating tags or commits.

### What to build

Add `yahnis-elsts/plugin-update-checker` (^5.x) to composer dependencies. Wire the update checker in the plugin bootstrap file using the public-repo constructor (no token, no dotenv), pointing at `https://github.com/morntag/wp-docsmanager`, branch `main`, with `enableReleaseAssets()`. Update `.release-it.json`: replace the existing `after:bump` composer-bump hook with a perl patch against the `Version:` line in `wp-docsmanager.php`. Update `.github/workflows/release.yml`: keep the existing PHPUnit/PHPStan/PHPCS gate job; in the release job drop the `.env`-creation step entirely; after `release-it` succeeds and a new tag exists, build a ZIP by rsyncing the repo into a staging folder named `wp-docsmanager/` (excluding dev files via a `.zipexclude` list), zipping it as `wp-docsmanager-v${VERSION}.zip`, and uploading to the release with `gh release upload`. Extract the version from the plugin header (`grep -Po "(?<=Version: ).*" wp-docsmanager.php`). Skip any `npm run build` step — the committed `editor.bundle.js` is shipped as-is. `composer install --no-dev --optimize-autoloader` runs before the rsync so production `vendor/` is inside the ZIP.

### Acceptance criteria

- [ ] `composer.json` requires `yahnis-elsts/plugin-update-checker: ^5.0`.
- [ ] The plugin bootstrap file instantiates PUC with the public repo URL, branch `main`, and release-assets enabled, without calling `setAuthentication()` and without loading any `.env` file.
- [ ] A manual workflow-dispatch dry-run completes without creating tags, commits, releases, or ZIP uploads, and logs the planned bump.
- [ ] A push to `main` containing a `feat:` or `fix:` commit triggers the workflow; the test job runs PHPCS / PHPStan / PHPUnit; on pass, the release job bumps the plugin header `Version:` line, commits as `chore(release): v${version}`, tags, creates a GitHub release, and uploads `wp-docsmanager-v${VERSION}.zip` as a release asset.
- [ ] The produced ZIP extracts to a single top-level folder `wp-docsmanager/` containing `wp-docsmanager.php`, `DocsManager.php`, `vendor/` (prod only), `assets/`, `views/`, `Models/`, `Services/`, `Module.php`, and `composer.json`. It does NOT contain: `.env`, `.git`, `.github`, `node_modules`, `tests`, `phpunit.xml`, `phpcs.xml`, `phpstan.neon`, `package.json`, `package-lock.json`, `CLAUDE.md`, `.claude`, `lefthook.yml`, `commitlint.config.js`, `.release-it.json`, `CHANGELOG.md`, `plans`.
- [ ] Pushes with only `chore:` / `docs:` / `wip:` commits complete the test job and exit the release job cleanly without creating a tag.
- [ ] The `chore(release): v*` commit pushed back to `main` does not retrigger the workflow (skip guard).
- [ ] On a test WP site running v0.3.0, after v0.4.0 is released, `Plugins` shows an "Update available" notice within 12 hours (PUC default cache window) or after a manual `Force check for updates` click; clicking `Update` installs the new version cleanly.

---

## Phase 4: README rewrite & baspo-detach guidance

**User stories**:
- As a new user discovering the repo, the README tells me how to install the plugin via ZIP, configure it, and integrate with my own capability system — without mentioning Composer libraries.
- As a baspo maintainer, the README's migration section tells me exactly which files to edit and what to delete to detach the bundled version.
- As a contributor or future-self, `CLAUDE.md` reflects the standalone-plugin architecture and no longer describes library-consumer semantics.

### What to build

Rewrite `README.md` from scratch as a plugin README with sections: what it is, installation (download ZIP from Releases → upload to WP → activate), configuration walkthrough (Settings → Documentation → pick plugin + paths + rescan), integration hooks (short examples for `morntag_docs_user_can`, `morntag_docs_modules_dir`, `morntag_docs_docs_dir`), custom documentation posts (hand-authored via the admin UI), and a "Migrating from bundled (mcc-baspo) installation" section. The migration section documents the three baspo-side edits: remove `morntag/wp-docsmanager` from baspo's `composer.json` requires and the VCS repository entry; delete `includes/DocsManagerBootstrap.php`; optionally re-hook `morntag_docs_user_can` in `morntag-custom-code.php` if non-admin role access is still wanted. Update `CLAUDE.md` to describe the new architecture: bootstrap via plugin header, settings-driven configuration, no `boot()` API. Remove library-era instructions and the `DocsManager::boot(config)` section.

### Acceptance criteria

- [ ] `README.md` contains no `composer require` installation instructions for end users and no `DocsManager::boot()` code examples.
- [ ] `README.md` includes a screenshot or described flow of the settings page.
- [ ] `README.md` includes a minimal working example for `morntag_docs_user_can` showing a role-to-cap mapping.
- [ ] `README.md` has a `## Migrating from a bundled (mcc-baspo) install` section with the three concrete edit steps.
- [ ] `CLAUDE.md` no longer references `DocsManager::boot(config)`, the `$pending_config` pattern, or "consumed as a Composer library."
- [ ] `CLAUDE.md` describes the `docsmanager_settings` option, the settings page, and the rename of UI-guard caps to `docsmanager_*_docs`.
- [ ] `CLAUDE.md` retains the "legacy `mcc_*` identifiers are frozen" rule covering CPT / taxonomy / post meta / CPT capability_type.
- [ ] A contributor reading only `README.md` + `CLAUDE.md` can install the plugin, configure it, and understand the architectural conventions without referencing this plan file.
