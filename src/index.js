/**
 * Editor side of the hm/theme-file source and its aliases, so bound images
 * show while editing.
 *
 * The editor cannot check whether a file exists, so it always points at the
 * active (child) theme, without the parent theme fallback the server has.
 */
// Provided by WordPress as the wp.blocks global, which the build maps this
// import to, so it is not installed here.
// eslint-disable-next-line import/no-unresolved
import { registerBlockBindingsSource } from '@wordpress/blocks';

const { sources = [], themeUri = '' } = window.hmThemeFileBinding || {};

/**
 * Decode a URL-encoded string, or return it unchanged when it is malformed.
 *
 * @param {string} value Encoded string.
 * @return {string} Decoded string.
 */
const safeDecode = ( value ) => {
	try {
		return decodeURIComponent( value );
	} catch {
		return value;
	}
};

/**
 * Check a path the same way as is_valid_path() in inc/namespace.php.
 *
 * @param {unknown} path Path from the binding arguments.
 * @return {boolean} True when the path can be resolved against the theme.
 */
const isValidPath = ( path ) =>
	typeof path === 'string' &&
	! /^[ \t\n\r/\\]*$/.test( path ) &&
	! path.includes( '\0' ) &&
	! path.startsWith( '//' ) &&
	! /^[a-z][a-z0-9+.-]*:/i.test( path ) &&
	! path
		.split( /[/\\]/ )
		.some( ( segment ) => safeDecode( segment ) === '..' );

sources.forEach( ( name ) =>
	registerBlockBindingsSource( {
		name,
		getValues( { bindings } ) {
			return Object.fromEntries(
				Object.entries( bindings ).map( ( [ attribute, { args } ] ) => [
					attribute,
					themeUri && isValidPath( args?.path )
						? `${ themeUri }/${ args.path.replace( /^\/+/, '' ) }`
						: undefined,
				] )
			);
		},
		canUserEditValue: () => false,
	} )
);
