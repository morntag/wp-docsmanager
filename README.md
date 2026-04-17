# wp-docsmanager

Standalone documentation manager for WordPress plugins. Provides a custom post type, admin UI, Tiptap-based Markdown editor, and filesystem scanner for per-module READMEs.

Extracted from [`morntag/mcc-baspo`](https://github.com/morntag/mcc-baspo) for reuse across projects.

## Features

- Module README discovery — auto-scans a configurable `<modules_dir>/*/README.md` glob
- Custom documentation via a hierarchical `mcc_documentation` CPT
- Tiptap WYSIWYG Markdown editor with autosave
- Full-text search across file-based and custom docs
- Parsedown Extra for frontmatter and HTML rendering
- Prism.js syntax highlighting
- Multisite-aware (main site only by default)

## Installation

Add to your host plugin's `composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "git@github.com:morntag/wp-docsmanager.git" }
  ],
  "require": {
    "morntag/wp-docsmanager": "dev-main"
  }
}
```

Then `composer update morntag/wp-docsmanager`.

For local development against a working copy:

```json
{
  "repositories": [
    { "type": "path", "url": "../wp-docsmanager", "options": { "symlink": true } }
  ]
}
```

## Bootstrap

The package does not self-register. Host plugins boot it explicitly after Composer's autoloader has loaded:

```php
<?php
use Morntag\WpDocsManager\DocsManager;

add_action( 'plugins_loaded', function () {
    if ( is_multisite() && 1 !== get_current_blog_id() ) {
        return;
    }

    $plugin_dir = plugin_dir_path( __FILE__ );

    DocsManager::boot( array(
        'modules_dir'   => $plugin_dir . 'includes/Modules/',
        'docs_dir'      => $plugin_dir . '.docs/',
        'allowed_roots' => array( $plugin_dir ),
    ) );
}, 20 );
```

### Config keys

| Key | Type | Description |
| --- | --- | --- |
| `modules_dir`   | `string`   | Directory whose immediate subfolders may contain a `README.md` that the scanner indexes. Pass an empty string to disable module scanning. |
| `docs_dir`      | `string`   | Root of a `.docs`-style Markdown tree. Pass an empty string to disable. |
| `allowed_roots` | `string[]` | Absolute paths whose descendants may be rendered by the viewer. The viewer rejects any `realpath()` that does not start with one of these roots. |

## Capability integration

DocsManager uses opaque capability strings (`mcc_access_docs`, `mcc_edit_docs`, `mcc_delete_docs`). Host plugins map them to their own capability system via the `morntag_docs_user_can` filter:

```php
add_filter( 'morntag_docs_user_can', function ( $result, string $cap ) {
    if ( is_bool( $result ) ) {
        return $result;
    }
    return my_role_system_has_cap( $cap ) || current_user_can( 'manage_options' );
}, 10, 2 );
```

Without the filter, access falls back to `current_user_can( 'manage_options' )`, which means administrators always have access out of the box.

## Fixed identifiers

The following identifiers are intentionally not configurable because changing them would orphan existing content in consuming installations:

- CPT slug: `mcc_documentation`
- Taxonomy slug: `mcc_doc_category`
- Admin menu page slug: `mcc-documentation`
- Post meta keys: `_mcc_doc_frontmatter`, `_mcc_doc_type`

These legacy `mcc_*` names are preserved for backwards compatibility with the `mcc-baspo` plugin that was the source of the extraction.

## Development

```bash
composer install          # PHP deps
npm install               # JS deps for the Tiptap editor
npm run build             # Bundles assets/src/editor.js → assets/js/editor.bundle.js
```

The compiled `assets/js/editor.bundle.js` is tracked in the repo so consumers get a ready-to-use package without running a JS toolchain.

### Code quality

```bash
composer phpcs            # Lint PHP (WordPress coding standards)
composer phpcbf           # Auto-fix violations
composer phpstan          # Static analysis (level 6)
composer test             # Unit tests (no WordPress bootstrap needed)
```

Rules are in `phpcs.xml` (WordPress-Extra + Core + Docs). Static analysis config is in `phpstan.neon`. Unit tests are in `tests/Unit/` with WordPress functions stubbed.

## License

GPL-2.0-or-later
