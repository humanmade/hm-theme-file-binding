<?php
/**
 * Plugin Name: HM Theme File Binding
 * Plugin URI: https://github.com/humanmade/hm-theme-file-binding
 * Description: Block bindings source that points a block attribute at a file in the active theme.
 * Version: __VERSION__
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Human Made
 * Author URI: https://humanmade.com
 * License: GPL-2.0-or-later
 * Text Domain: hm-theme-file-binding
 *
 * @package hm-theme-file-binding
 */

namespace HM\ThemeFileBinding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/inc/namespace.php';

bootstrap();
