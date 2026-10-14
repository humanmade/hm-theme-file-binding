<?php
/**
 * Theme file block bindings source.
 *
 * @package hm-theme-file-binding
 */

namespace HM\ThemeFileBinding;

/**
 * Name of the block bindings source.
 */
const SOURCE = 'hm/theme-file';

/**
 * Hook the plugin into WordPress.
 */
function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_sources' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );
}

/**
 * Get the source names to register: hm/theme-file plus any aliases.
 *
 * @return string[] Source names.
 */
function get_source_names(): array {
	/**
	 * Filters extra source names that resolve the same way as hm/theme-file.
	 *
	 * Lets a theme keep content that uses its own source name working while
	 * it moves to hm/theme-file.
	 *
	 * @param string[] $aliases Source names, such as 'my-theme/theme-file'.
	 */
	$aliases = apply_filters( 'hm_theme_file_binding_aliases', [] );
	$aliases = array_filter( (array) $aliases, 'is_string' );

	return array_values( array_unique( array_merge( [ SOURCE ], $aliases ) ) );
}

/**
 * Register the block bindings source and its aliases.
 */
function register_sources(): void {
	foreach ( get_source_names() as $name ) {
		register_block_bindings_source(
			$name,
			[
				'label'              => __( 'Theme file', 'hm-theme-file-binding' ),
				'get_value_callback' => __NAMESPACE__ . '\\get_value',
			]
		);
	}
}

/**
 * Load the editor side of the sources, with their names and the theme's URL.
 */
function enqueue_editor_assets(): void {
	$asset_file = dirname( __DIR__ ) . '/build/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require $asset_file;

	wp_enqueue_script(
		'hm-theme-file-binding',
		plugins_url( 'build/index.js', PLUGIN_FILE ),
		$asset['dependencies'],
		$asset['version'],
		true
	);

	wp_add_inline_script(
		'hm-theme-file-binding',
		sprintf(
			'window.hmThemeFileBinding = %s;',
			wp_json_encode(
				[
					'sources'  => get_source_names(),
					'themeUri' => get_stylesheet_directory_uri(),
				]
			)
		),
		'before'
	);
}

/**
 * Resolve a binding to the URL of a file in the active theme.
 *
 * A child theme's copy of the file wins over the parent theme's.
 *
 * @param array $source_args Binding arguments. `path` is relative to the theme.
 * @return string|null The file's URL, or null when the path is not allowed or
 *                     the file does not exist.
 */
function get_value( array $source_args ): ?string {
	$path = $source_args['path'] ?? null;

	if ( ! is_valid_path( $path ) ) {
		return null;
	}

	if ( ! file_exists( get_theme_file_path( $path ) ) ) {
		return null;
	}

	return get_theme_file_uri( $path );
}

/**
 * Check that a path is a non-empty relative path that stays inside the theme.
 *
 * Rejects URLs with a scheme (http:, data:), protocol-relative URLs, null
 * bytes and `..` segments, including URL-encoded ones.
 *
 * @param mixed $path Path from the binding arguments.
 * @return bool True when the path can be resolved against the theme.
 */
function is_valid_path( $path ): bool {
	if ( ! is_string( $path ) || '' === trim( $path, " \t\n\r/\\" ) || str_contains( $path, "\0" ) ) {
		return false;
	}

	if ( str_starts_with( $path, '//' ) || preg_match( '/^[a-z][a-z0-9+.-]*:/i', $path ) ) {
		return false;
	}

	foreach ( preg_split( '#[/\\\\]#', $path ) as $segment ) {
		if ( '..' === rawurldecode( $segment ) ) {
			return false;
		}
	}

	return true;
}
