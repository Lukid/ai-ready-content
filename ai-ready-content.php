<?php
/**
 * Plugin Name: AI-Ready Content
 * Plugin URI:  https://github.com/lucabaroncini/ai-ready-content
 * Description: Generates markdown versions of posts and pages for LLM and AI agent consumption, with YAML frontmatter, .md endpoints, and llms.txt support.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author:      Luca Baroncini
 * Author URI:  https://github.com/lucabaroncini
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-ready-content
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIRC_VERSION', '1.0.0' );
define( 'AIRC_PLUGIN_FILE', __FILE__ );
define( 'AIRC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIRC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, [ AIRC\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ AIRC\Plugin::class, 'deactivate' ] );

add_action( 'plugins_loaded', function () {
	AIRC\Plugin::instance();
} );
