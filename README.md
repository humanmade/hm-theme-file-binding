# HM Theme File Binding

A block bindings source, `hm/theme-file`, that points a block attribute at a file in the active theme. Patterns and templates can then use images that ship with the theme, without a hard-coded URL that changes between environments.

Requires WordPress 6.7 or later and PHP 8.1 or later. WordPress 6.7 is the first version that lets the editor register a bindings source, which this plugin needs to show bound images while editing.

## Installation

Install with Composer:

```sh
composer require humanmade/hm-theme-file-binding
```

Or download the ZIP from a release and install it as a normal plugin. A release ZIP has the built editor script. A plain clone of the source does not, so run `npm install && npm run build` first.

## Usage

Bind an attribute to `hm/theme-file` and give a `path` relative to the theme folder. This image block shows `assets/images/hero.webp` from the active theme:

```html
<!-- wp:image {"sizeSlug":"full","linkDestination":"none","metadata":{"bindings":{"url":{"source":"hm/theme-file","args":{"path":"assets/images/hero.webp"}}}}} -->
<figure class="wp-block-image size-full"><img alt=""/></figure>
<!-- /wp:image -->
```

The binding works on any attribute that supports block bindings. It is most useful for the image block's `url`.

### How the path resolves

The plugin passes the path to `get_theme_file_uri()`. If the active theme is a child theme and has the file, the child theme's copy wins. If not, the parent theme's copy is used.

The binding gives no value, and the block keeps its own attribute, when:

- the path is empty or not a string,
- the path is an absolute or protocol-relative URL, or uses a scheme such as `data:`,
- the path has a `..` segment, also when it is URL-encoded as `%2e%2e`,
- the file does not exist in the child or parent theme.

### In the editor

The editor shows the bound file too. It cannot check whether a file exists, so it always uses the active theme's folder. In a child theme, a file that exists only in the parent theme shows on the front end but appears broken in the editor. Copy the file into the child theme if you need it to show while editing.

Editors cannot change the bound value from the block's settings. Edit the `path` in the block markup.

## Aliases for existing content

The `hm_theme_file_binding_aliases` filter registers extra source names that work exactly like `hm/theme-file`, in PHP and in the editor. Use it to keep content working that uses a theme's own source name:

```php
add_filter( 'hm_theme_file_binding_aliases', function ( array $aliases ): array {
	$aliases[] = 'my-theme/theme-file';
	return $aliases;
} );
```

Add the filter in the theme's `functions.php` or in a plugin. It must run before `init`.

## Moving a theme to this plugin

If a theme registers its own theme file source, move it over in three steps.

1. In one change: require this plugin, add the alias filter with the theme's old source name, and remove the theme's own source. Remove both the PHP `register_block_bindings_source()` call and the editor `registerBlockBindingsSource()` call. If either stays, WordPress warns that the source is already registered.
2. Change the `source` in the theme's patterns, templates and template parts from the old name to `hm/theme-file`. Content saved in the database may still use the old name, so search the posts table for it too.
3. When nothing uses the old name any more, remove the alias filter.

## Development

```sh
composer install
composer lint      # PHPCS with the Human Made coding standards
composer test      # PHPUnit

npm install
npm run build      # build the editor script into build/
npm run start      # rebuild on change
npm run lint:js
```

The `build/` folder is not in the source tree. CI builds it.

## Releasing

A push to `main` builds the editor script into the `release` branch. To make a versioned release, run the **Release** workflow from the Actions tab with a version like `1.2.3`. It builds the plugin, replaces `__VERSION__` in the plugin header, creates a tag that never moves, and attaches a ZIP to a GitHub release. Composer installs from the tag.
