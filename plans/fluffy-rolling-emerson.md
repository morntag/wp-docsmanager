# Set up phpcs, phpcbf, and phpstan for wp-docsmanager

## Context

wp-docsmanager was recently extracted from mcc-baspo and has no code quality tooling. The 9 PHP files already follow WordPress coding standards (with inline phpcs:ignore comments where needed), but there's no config to enforce them. We're mirroring mcc-baspo's setup — WordPress-Extra/Core/Docs rulesets for phpcs, and phpstan with the WordPress extension.

## Files to create/modify

| File | Action |
|------|--------|
| `composer.json` | Add require-dev, scripts, allow-plugins |
| `phpcs.xml` | **New** — WordPress coding standards ruleset |
| `phpstan.neon` | **New** — Static analysis config |
| `phpstan-bootstrap.php` | **New** — Bootstrap with WP constants |
| `DocsManager.php` | Remove `phpcs:ignoreFile` on line 1 |
| `CLAUDE.md` | Add code quality commands section |

## Step 1 — Update `composer.json`

Add `require-dev`, `scripts`, and `config.allow-plugins`:

```json
"require-dev": {
    "phpstan/phpstan": "*",
    "phpstan/extension-installer": "*",
    "squizlabs/php_codesniffer": "^3.10",
    "szepeviktor/phpstan-wordpress": "*",
    "wp-coding-standards/wpcs": "^3.1"
},
"scripts": {
    "phpstan": "phpstan analyse --memory-limit=1G",
    "phpcs": "./vendor/bin/phpcs -d memory_limit=1G",
    "phpcbf": "./vendor/bin/phpcbf -d memory_limit=1G"
},
"config": {
    "sort-packages": true,
    "allow-plugins": {
        "dealerdirect/phpcodesniffer-composer-installer": true,
        "phpstan/extension-installer": true
    }
}
```

## Step 2 — Create `phpcs.xml`

Mirror mcc-baspo's ruleset ([phpcs.xml](/Users/brianboy/dev/wp-local/sites/baspo/wp-content/plugins/mcc-baspo/phpcs.xml)) with these differences:
- Add `/node_modules/*` and `/assets/*` exclude patterns
- Trim custom capabilities to the 4 docs-related ones only

## Step 3 — Create `phpstan.neon`

Start at **level 6** (not 8). Rationale: codebase has never been through phpstan; level 8 would produce a flood of errors. Bump incrementally later.

Paths must explicitly list root-level files + subdirectories since PSR-4 maps to root:
- `DocsManager.php`, `Module.php`, `Models/`, `Services/`

Exclude `views/` — template files use variables injected from the including scope, which phpstan can't resolve.

## Step 4 — Create `phpstan-bootstrap.php`

Minimal version of mcc-baspo's ([phpstan-bootstrap.php](/Users/brianboy/dev/wp-local/sites/baspo/wp-content/plugins/mcc-baspo/phpstan-bootstrap.php)). Only define `WPINC` and `ABSPATH` + load autoloader. No plugin-specific constants needed (this is a library).

## Step 5 — Remove `phpcs:ignoreFile` from `DocsManager.php`

Line 1: `<?php // phpcs:ignoreFile` → `<?php`

The blanket ignore suppresses all rules on a 700-line file. Existing granular inline ignores (lines 271, 304, 388, 398) already cover the legitimate suppressions.

## Step 6 — Run `composer update` and fix violations

```bash
composer update                    # Install dev deps
composer phpcbf                    # Auto-fix what it can
composer phpcs                     # Check remaining issues — add targeted inline ignores if needed
composer phpstan                   # Fix or suppress any errors
```

## Step 7 — Update `CLAUDE.md`

Add code quality commands to the existing "Build Commands" section or a new "Code Quality" section.

## Out of scope

- Git pre-commit hooks (add later)
- PHPUnit / test infrastructure (separate task)
- CI pipeline / GitHub Actions (separate task)
- Bumping phpstan to level 8 (incremental later)

## Verification

1. `composer phpcs` exits 0 (or only reports intentionally-ignored lines)
2. `composer phpstan` exits 0
3. `composer phpcbf` auto-fixes and re-running `composer phpcs` still passes
