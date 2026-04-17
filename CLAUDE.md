# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

A standalone WordPress documentation manager extracted from the `mcc-baspo` plugin (`morntag/mcc-baspo`). It is consumed as a Composer library — it does **not** self-register. Host plugins boot it explicitly via `DocsManager::boot(config)` on `plugins_loaded`.

The primary consumer is `mcc-baspo`, which wires it in via `includes/DocsManagerBootstrap.php`.

## Build Commands

```bash
composer install                # PHP deps (Parsedown, Symfony YAML)
npm install                     # JS deps (Tiptap editor)
npm run build                   # esbuild: assets/src/editor.js → assets/js/editor.bundle.js
```

The compiled `editor.bundle.js` is tracked in git so consumers skip the JS toolchain.

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

- **commit-msg** — [commitlint](https://commitlint.js.org/) validates conventional commits. Allowed types: `feat`, `fix`, `docs`, `chore`, `ci`, `refactor`, `style`, `agent`, `wip`
- **pre-commit** — PHPCBF auto-fix + PHPCS on staged PHP files; Biome auto-fix + check on staged JS files
- **pre-push** — Branch naming: `main`, `dev`, `feature/*`, `bugfix/*`, `hotfix/*` (lowercase, hyphens only)

After cloning, run `npm install` — lefthook installs automatically.

## Release Workflow

Automated via [release-it](https://github.com/release-it/release-it) + GitHub Actions (`.github/workflows/release.yml`):

1. Push to `main` triggers CI
2. **Gate**: PHPUnit, PHPStan, PHPCS must all pass
3. **Release**: release-it analyzes conventional commits, determines semver bump, updates `composer.json` version, generates `CHANGELOG.md`, creates git tag + GitHub Release
4. Release commits (`chore(release): v*`) are auto-skipped

Only `feat` and `fix` commits trigger a release. `chore`, `docs`, `style`, `wip` are no-ops.

Manual dry-run: trigger the workflow via GitHub Actions UI with the dry-run checkbox, or locally with `npm run release:dry`.

## Architecture

### Bootstrap Flow

1. Host plugin calls `DocsManager::boot(array $config)` — stores config in static `$pending_config`
2. `boot()` calls `instance()` (singleton via `Module` base class)
3. `Module::__construct()` calls `register_hooks()`, `register_filters()`, `init()`
4. `DocsManager::init()` consumes pending config, instantiates all services

### Class Hierarchy

```
Module (abstract — singleton pattern, declarative $hooks/$filters arrays)
  └─ DocsManager (main orchestrator, ~700 lines)
       ├─ Models\Documentation — CPT & taxonomy registration
       └─ Services\
            ├─ FileScanner — scans modules_dir/*/README.md and docs_dir/**/*.md, transient-cached 1h
            ├─ MarkdownParser — Parsedown Extra wrapper, frontmatter via Symfony YAML, TOC generation
            └─ SearchService — unified full-text search across posts + scanned files, relevance scoring
```

### Namespace & Autoloading

PSR-4: `Morntag\WpDocsManager\` maps to the repo root. Files directly match their namespace path.

### Legacy mcc_* Identifiers

These are **not configurable** — changing them would orphan existing data in consuming installations:

- CPT: `mcc_documentation`, Taxonomy: `mcc_doc_category`
- Menu slug: `mcc-documentation`
- Post meta: `_mcc_doc_frontmatter`, `_mcc_doc_type`
- Capabilities: `mcc_access_docs`, `mcc_edit_docs`, `mcc_delete_docs`
- AJAX actions: `morntag_docs_search`, `morntag_docs_list`

### Admin UI

Three views in `views/`:
- `admin-page.view.php` — sidebar navigation (search, collapsible sections for module/dev/custom docs)
- `viewer.view.php` — rendered Markdown with auto-generated TOC
- `editor.view.php` — Tiptap WYSIWYG form (Markdown in/out)

JS in `assets/js/admin.js` handles search (debounced AJAX), sidebar collapse (localStorage), print, TOC scroll.

### Tiptap Editor

Source: `assets/src/editor.js` with custom extensions in `assets/src/extensions/` (iframe, image, video) and modals in `assets/src/components/` (doc-picker, media). Built via esbuild into a single IIFE bundle.

### Security Model

- Capability check via `morntag_docs_user_can` filter; falls back to `manage_options`
- File viewer validates paths against `allowed_roots` using `realpath()`
- Nonces: `morntag_docs_save`, `morntag_docs_delete_{id}`, `morntag_docs_nonce` (AJAX)
- `wp_kses_post()` on save; extended allowed tags for iframe/video/source

### Config Keys (passed to boot())

| Key | Purpose |
|-----|---------|
| `modules_dir` | Glob target for `*/README.md` module docs |
| `docs_dir` | Root of `.docs/` Markdown file tree |
| `allowed_roots` | Whitelist for viewer `realpath()` check |
