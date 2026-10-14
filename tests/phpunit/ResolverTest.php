<?php
/**
 * Tests for the theme file resolver and source registration.
 *
 * @package hm-theme-file-binding
 */

namespace HM\ThemeFileBinding\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

use function HM\ThemeFileBinding\get_source_names;
use function HM\ThemeFileBinding\get_value;
use function HM\ThemeFileBinding\register_sources;

class ResolverTest extends TestCase {

	private const CHILD_URI = 'https://example.com/wp-content/themes/child';

	private const PARENT_URI = 'https://example.com/wp-content/themes/parent';

	private string $fixture_dir;

	private string $parent_dir;

	private string $child_dir;

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();

		$this->fixture_dir = sys_get_temp_dir() . '/hm-theme-file-binding-' . uniqid();
		$this->parent_dir  = $this->fixture_dir . '/parent';
		$this->child_dir   = $this->fixture_dir . '/child';

		mkdir( $this->parent_dir . '/assets', 0777, true );
		mkdir( $this->child_dir . '/assets', 0777, true );
		touch( $this->parent_dir . '/assets/both.webp' );
		touch( $this->child_dir . '/assets/both.webp' );
		touch( $this->parent_dir . '/assets/parent-only.webp' );

		$child_dir  = $this->child_dir;
		$parent_dir = $this->parent_dir;

		Functions\when( 'get_stylesheet_directory' )->justReturn( $child_dir );
		Functions\when( 'get_template_directory' )->justReturn( $parent_dir );
		Functions\when( 'get_stylesheet_directory_uri' )->justReturn( self::CHILD_URI );
		Functions\when( 'get_template_directory_uri' )->justReturn( self::PARENT_URI );

		// Copies of core's child-then-parent lookup.
		Functions\when( 'get_theme_file_path' )->alias(
			function ( $file = '' ) use ( $child_dir, $parent_dir ) {
				$file = ltrim( $file, '/' );

				if ( empty( $file ) ) {
					return $child_dir;
				}

				if ( $parent_dir !== $child_dir && file_exists( $child_dir . '/' . $file ) ) {
					return $child_dir . '/' . $file;
				}

				return $parent_dir . '/' . $file;
			}
		);
		Functions\when( 'get_theme_file_uri' )->alias(
			function ( $file = '' ) use ( $child_dir, $parent_dir ) {
				$file = ltrim( $file, '/' );

				if ( empty( $file ) ) {
					return self::CHILD_URI;
				}

				if ( $parent_dir !== $child_dir && file_exists( $child_dir . '/' . $file ) ) {
					return self::CHILD_URI . '/' . $file;
				}

				return self::PARENT_URI . '/' . $file;
			}
		);
	}

	protected function tear_down() {
		$this->remove_dir( $this->fixture_dir );

		Monkey\tearDown();
		parent::tear_down();
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $dir . '/' . $entry;

			if ( is_dir( $path ) ) {
				$this->remove_dir( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}

	public function test_valid_path_resolves_to_url() {
		$url = self::CHILD_URI . '/assets/both.webp';

		$this->assertSame( $url, get_value( [ 'path' => 'assets/both.webp' ] ) );
		$this->assertSame( $url, get_value( [ 'path' => '/assets/both.webp' ] ) );

		touch( $this->child_dir . '/assets/a..b.webp' );
		$this->assertSame(
			self::CHILD_URI . '/assets/a..b.webp',
			get_value( [ 'path' => 'assets/a..b.webp' ] )
		);
	}

	public function test_child_theme_file_wins_over_parent() {
		$this->assertSame(
			self::CHILD_URI . '/assets/both.webp',
			get_value( [ 'path' => 'assets/both.webp' ] )
		);
		$this->assertSame(
			self::PARENT_URI . '/assets/parent-only.webp',
			get_value( [ 'path' => 'assets/parent-only.webp' ] )
		);
		$this->assertNull( get_value( [ 'path' => 'assets/missing.webp' ] ) );
	}

	/**
	 * @dataProvider traversal_paths
	 */
	public function test_traversal_returns_null( string $path ) {
		$this->assertNull( get_value( [ 'path' => $path ] ) );
	}

	public static function traversal_paths(): array {
		return [
			'parent dir'             => [ '../wp-config.php' ],
			'nested parent dir'      => [ 'assets/../../wp-config.php' ],
			'backslash separators'   => [ 'assets\..\..\wp-config.php' ],
			'encoded dots'           => [ '%2e%2e/wp-config.php' ],
			'encoded dots in middle' => [ 'assets/%2E%2E/x.webp' ],
			'only dots'              => [ '..' ],
		];
	}

	/**
	 * @dataProvider absolute_urls
	 */
	public function test_absolute_url_returns_null( string $path ) {
		$this->assertNull( get_value( [ 'path' => $path ] ) );
	}

	public static function absolute_urls(): array {
		return [
			'https'             => [ 'https://example.com/a.webp' ],
			'http'              => [ 'http://example.com/a.webp' ],
			'protocol relative' => [ '//example.com/a.webp' ],
			'data URI'          => [ 'data:image/png;base64,AAAA' ],
			'javascript URI'    => [ 'javascript:alert(1)' ],
		];
	}

	/**
	 * @dataProvider invalid_args
	 */
	public function test_empty_or_invalid_input_returns_null( array $source_args ) {
		$this->assertNull( get_value( $source_args ) );
	}

	public static function invalid_args(): array {
		return [
			'no path key'   => [ [] ],
			'empty string'  => [ [ 'path' => '' ] ],
			'slash only'    => [ [ 'path' => '/' ] ],
			'whitespace'    => [ [ 'path' => '   ' ] ],
			'null'          => [ [ 'path' => null ] ],
			'integer'       => [ [ 'path' => 123 ] ],
			'array'         => [ [ 'path' => [ 'a' ] ] ],
			'null byte'     => [ [ 'path' => "assets/both.webp\0.php" ] ],
		];
	}

	public function test_aliases_are_registered_as_sources() {
		Functions\stubTranslationFunctions();
		Filters\expectApplied( 'hm_theme_file_binding_aliases' )
			->twice()
			->andReturn( [ 'my-theme/theme-file', 'hm/theme-file', 42 ] );

		$this->assertSame( [ 'hm/theme-file', 'my-theme/theme-file' ], get_source_names() );

		$calls = [];
		Functions\expect( 'register_block_bindings_source' )
			->twice()
			->andReturnUsing(
				function ( $name, $args ) use ( &$calls ) {
					$calls[] = [ $name, $args ];
					return true;
				}
			);

		register_sources();

		$this->assertSame(
			[ 'hm/theme-file', 'my-theme/theme-file' ],
			array_column( $calls, 0 )
		);

		foreach ( $calls as $call ) {
			$this->assertSame( 'HM\ThemeFileBinding\get_value', $call[1]['get_value_callback'] );
		}
	}
}
