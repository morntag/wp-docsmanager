# WP Docs Manager

A standalone WordPress plugin for documenting other WordPress plugins. Auto-discovers `README.md` files inside any active plugin's subdirectories, renders them in a custom admin UI, and lets you author additional docs through a Tiptap WYSIWYG editor backed by a hierarchical custom post type.

Originally extracted from `morntag/mcc-baspo`; now ships as an independent open-source plugin.

## Features

- **File-based scanning** — point it at any installed plugin and it auto-detects per-module `README.md` files plus a recursive `.docs/`-style Markdown tree
- **Custom documentation** — hierarchical `mcc_documentation` CPT with categories, frontmatter, and revisions
- **Tiptap Markdown editor** — WYSIWYG authoring with image uploads, embedded videos, and internal-link picker
- **Search** — unified full-text search across both file-based and custom docs
- **Per-site multisite** — activates independently on each subsite
- **Self-updating** — pulls updates straight from GitHub Releases (Phase 3)

## Installation

Until the plugin is on the WordPress.org repository:

1. Download the latest `wp-docsmanager-vX.Y.Z.zip` from [Releases](https://github.com/morntag/wp-docsmanager/releases).
2. In wp-admin, go to **Plugins → Add New → Upload Plugin** and upload the ZIP.
3. Click **Activate**.

Once installed, future versions auto-update through WordPress's normal update flow (no GitHub token required — the repo is public).

## Configuration

After activation, go to **Documentation → Settings**.

The plugin doesn't scan anything until you configure it.

| Field | What it does |
|---|---|
| **Source plugin** | Dropdown of installed plugins. Pick the one whose docs you want indexed. |
| **Scan module READMEs** | If on, walks `<plugin>/<modules subpath>/*/README.md` (one level deep). Use this for plugins that organise functionality into per-module subdirectories. |
| **Module READMEs subpath** | Path relative to the plugin root. Defaults to `includes/Modules`. |
| **Scan docs tree** | If on, walks `<plugin>/<docs subpath>/**/*.md` (recursive). Use this for `.docs/` knowledge-base trees. |
| **Docs tree subpath** | Path relative to the plugin root. Defaults to `.docs`. |

Both scans are independent — enable either, both, or neither. Subpath inputs reject `..`, absolute paths, and backslashes.

A **Rescan now** button on the Documentation page clears the 1-hour transient cache when you've just edited a file.

The Documentation page itself shows your scanned files plus any custom docs you've authored via the admin UI.

## Capability integration

The plugin checks three capability strings via `current_user_can()`:

- `docsmanager_access_docs` — view the Documentation menu and read docs
- `docsmanager_edit_docs` — create and edit custom docs
- `docsmanager_delete_docs` — delete custom docs

On activation, all three are granted to the `administrator` role.

To grant access to other roles (Editors, custom roles, etc.) without modifying role caps in the DB, hook the `morntag_docs_user_can` filter:

```php
add_filter( 'morntag_docs_user_can', function ( $result, string $cap ) {
    if ( is_bool( $result ) ) {
        return $result;
    }

    // Example: Editors get read access; custom 'docs_manager' role gets full access.
    if ( current_user_can( 'editor' ) && 'docsmanager_access_docs' === $cap ) {
        return true;
    }
    if ( current_user_can( 'docs_manager' ) ) {
        return true;
    }

    return null; // Fall back to manage_options.
}, 10, 2 );
```

The filter receives the cap name and a current resolution. Return `true`/`false` to short-circuit, or `null` to defer to the next filter or the `manage_options` fallback.

## Programmatic configuration (path-override filters)

If you want to set the scan paths from code instead of through the settings UI (for environments where the DB option can't be relied on), use these filters:

```php
add_filter( 'morntag_docs_modules_dir', function () {
    return WP_PLUGIN_DIR . '/my-plugin/includes/Modules';
} );

add_filter( 'morntag_docs_docs_dir', function () {
    return WP_PLUGIN_DIR . '/my-plugin/.docs';
} );
```

When a filter is registered, the settings page shows the field as read-only with an "Overridden by filter" notice.

## Migrating from a bundled (mcc-baspo) install

Until 2026-04-17, this code was a Composer library consumed by `mcc-baspo`. If your `mcc-baspo` install bundles `morntag/wp-docsmanager`, switch to the standalone plugin in three steps:

1. **Detach the Composer dependency in `mcc-baspo`:**
   - Remove `"morntag/wp-docsmanager": "^0.1"` from `composer.json` `require`
   - Remove the corresponding `repositories` entry pointing at this repo
   - Run `composer update`

2. **Delete the bootstrap file** at `includes/DocsManagerBootstrap.php` and any `require` of it.

3. **Install this plugin** following the [Installation](#installation) steps above, then go to **Documentation → Settings** and configure: select `mcc-baspo` from the dropdown, leave `includes/Modules` and `.docs` as the subpaths, save.

**No DB migration is required.** The CPT, taxonomy, and post-meta keys are deliberately frozen at their original `mcc_*` names, so all existing custom documentation posts and categories appear automatically once the standalone plugin activates.

If your baspo install previously hooked `morntag_docs_user_can` to map docs caps onto baspo's `Capabilities` module (so non-admin roles could access docs), re-add that hook directly in baspo's `morntag-custom-code.php` after detaching — it's only ~10 lines.

## Frozen identifiers

These are intentionally not configurable. Renaming them would orphan existing content in installations that originated from `mcc-baspo`:

- CPT slug: `mcc_documentation`
- Taxonomy slug: `mcc_doc_category`
- Admin menu page slug: `mcc-documentation`
- Post meta keys: `_mcc_doc_frontmatter`, `_mcc_doc_type`, `_mcc_doc_source_path`, `_mcc_doc_order`, `_mcc_doc_readonly`
- CPT `capability_type`: `['mcc_doc', 'mcc_docs']`

The user-facing capability strings (the ones you grant to roles) were renamed during the standalone conversion: `mcc_*_docs` → `docsmanager_*_docs`. CPT/taxonomy/meta names stayed the same so data is preserved.

## Development

```bash
composer install          # PHP deps
npm install               # JS deps for the Tiptap editor
npm run build             # Bundles assets/src/editor.js → assets/js/editor.bundle.js
```

The compiled `assets/js/editor.bundle.js` is tracked in git so end users skip the JS toolchain.

### Code quality

```bash
composer phpcs            # Lint PHP (WordPress coding standards)
composer phpcbf           # Auto-fix violations
composer phpstan          # Static analysis (level 6)
composer test             # PHPUnit unit tests (no WordPress bootstrap needed)
```

Configs: `phpcs.xml` (WordPress-Extra + Core + Docs), `phpstan.neon` (level 6), `phpunit.xml` (unit tests under `tests/Unit/` with WP functions stubbed via `tests/bootstrap.php`).

### Git hooks

[Lefthook](https://github.com/evilmartians/lefthook) installs automatically after `npm install`:

- **commit-msg** — [commitlint](https://commitlint.js.org/) enforces conventional commits (`feat`, `fix`, `docs`, `chore`, `refactor`, etc.)
- **pre-commit** — PHPCS auto-fix + check on staged PHP, Biome check on staged JS
- **pre-push** — branch naming check (`main`, `dev`, `feature/*`, `bugfix/*`, `hotfix/*`)

### Releases

GitHub Actions builds and publishes a release ZIP on every push to `main`:

1. Test gate: PHPCS + PHPStan + PHPUnit must pass
2. [release-it](https://github.com/release-it/release-it) inspects conventional commits, determines the semver bump, updates the plugin header `Version:` line, generates `CHANGELOG.md`, creates a git tag and a GitHub Release
3. CI rsyncs the repo into a clean staging dir (excluding dev tooling), zips it as `wp-docsmanager-vX.Y.Z.zip`, and uploads it as a release asset

Sites running the plugin auto-update via [yahnis-elsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker), which polls the public Releases API (no token needed).

Only `feat:` and `fix:` commits trigger a release. `chore`, `docs`, `style`, `wip` are no-ops.

## License

GPL-2.0-or-later
