<?php
/**
 * Main plugin orchestrator.
 */

namespace AIRC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Admin\PreviewMetaBox;
use AIRC\Admin\SettingsPage;
use AIRC\Cache\TransientCache;
use AIRC\CLI\Commands;
use AIRC\Converter\ContentPreparer;
use AIRC\Converter\FrontmatterGenerator;
use AIRC\Converter\MarkdownConverter;
use AIRC\Endpoint\LlmsTxtEndpoint;
use AIRC\Endpoint\PostEndpoint;
use AIRC\Helpers\PostTypeHelper;
use AIRC\Integration\AlternateLink;
use AIRC\Integration\RobotsTxt;
use AIRC\Router\ContentNegotiator;
use AIRC\Router\RewriteHandler;

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_components();
	}

	private function init_components(): void {
		$helper      = new PostTypeHelper();
		$cache       = new TransientCache();
		$preparer    = new ContentPreparer();
		$converter   = new MarkdownConverter();
		$frontmatter = new FrontmatterGenerator();

		$post_endpoint = new PostEndpoint( $preparer, $converter, $frontmatter, $cache, $helper );

		new RewriteHandler();
		new ContentNegotiator( $post_endpoint, $helper );
		new LlmsTxtEndpoint( $helper, $cache );
		new AlternateLink( $helper );
		new RobotsTxt();

		if ( is_admin() ) {
			new SettingsPage( $helper, $cache );
			new PreviewMetaBox( $helper, $cache, $preparer, $converter, $frontmatter );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'airc', new Commands( $helper, $cache, $preparer, $converter, $frontmatter ) );
		}
	}

	public static function activate(): void {
		if ( false === get_option( 'airc_settings' ) ) {
			update_option( 'airc_settings', self::get_defaults() );
		}
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function get_defaults(): array {
		return [
			'enabled_post_types'         => [ 'post', 'page' ],
			'enable_content_negotiation' => true,
			'enable_llms_txt'            => true,
			'enable_alternate_links'     => true,
			'enable_robots_txt'          => true,
			'cache_ttl'                  => 86400,
			'llms_txt_post_limit'        => 100,
			'frontmatter_meta_keys'      => '',
			'image_handling'             => 'keep',
		];
	}

	public static function get_settings(): array {
		$settings = get_option( 'airc_settings', [] );
		return wp_parse_args( $settings, self::get_defaults() );
	}
}
