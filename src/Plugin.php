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
use AIRC\Endpoint\LlmsFullTxtEndpoint;
use AIRC\Endpoint\LlmsTxtEndpoint;
use AIRC\Endpoint\PostEndpoint;
use AIRC\Endpoint\SitemapEndpoint;
use AIRC\Helpers\PostTypeHelper;
use AIRC\Integration\AlternateLink;
use AIRC\Integration\RobotsTxt;
use AIRC\Integration\ShareButton;
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
		self::maybe_migrate_settings();
		$this->init_components();
	}

	private function init_components(): void {
		// Flush rewrite rules on version change (e.g., new endpoints added).
		$stored_version = get_option( 'airc_version', '0' );
		if ( version_compare( $stored_version, AIRC_VERSION, '<' ) ) {
			add_action( 'init', 'flush_rewrite_rules', 99 );
			update_option( 'airc_version', AIRC_VERSION );
		}

		$helper      = new PostTypeHelper();
		$cache       = new TransientCache();
		$preparer    = new ContentPreparer();
		$converter   = new MarkdownConverter();
		$frontmatter = new FrontmatterGenerator();

		$post_endpoint = new PostEndpoint( $preparer, $converter, $frontmatter, $cache, $helper );

		new RewriteHandler();
		new ContentNegotiator( $post_endpoint, $helper );
		new LlmsTxtEndpoint( $helper, $cache );
		new LlmsFullTxtEndpoint( $helper, $cache );
		new SitemapEndpoint( $helper, $cache );
		new AlternateLink( $helper );
		new ShareButton( $helper );
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
			'enable_llms_full_txt'       => true,
			'enable_alternate_links'     => true,
			'enable_robots_txt'          => true,
			'cache_ttl'                  => 86400,
			'llms_txt_curated_limit'     => 10,
			'llms_txt_optional_limit'    => 10,
			'llms_txt_show_taxonomies'   => true,
			'llms_txt_post_limit'        => 100,
			'llms_full_txt_post_limit'   => 100,
			'frontmatter_meta_keys'      => '',
			'image_handling'             => 'keep',
			'show_protected_teaser'      => false,
			'enable_share_button'        => false,
			'share_button_position'      => 'bottom-right',
			'share_button_label'         => '',
			'share_services'             => [ 'markdown', 'chatgpt', 'claude', 'perplexity' ],
			'share_prompt_template'      => 'Read and summarize the following page: {url}',
		];
	}

	/**
	 * Migrate settings from pre-curated format.
	 * Maps old llms_txt_post_limit to new llms_full_txt_post_limit.
	 */
	private static function maybe_migrate_settings(): void {
		$settings = get_option( 'airc_settings', [] );

		// Already migrated or fresh install.
		if ( isset( $settings['llms_txt_curated_limit'] ) ) {
			return;
		}

		// If old llms_txt_post_limit exists, use it as the new full limit.
		if ( isset( $settings['llms_txt_post_limit'] ) ) {
			$settings['llms_full_txt_post_limit'] = (int) $settings['llms_txt_post_limit'];
		}

		$settings['llms_txt_curated_limit']  = 10;
		$settings['llms_txt_optional_limit'] = 10;
		$settings['enable_llms_full_txt']    = true;
		$settings['llms_txt_show_taxonomies'] = true;

		update_option( 'airc_settings', $settings );
	}

	public static function get_settings(): array {
		$settings = get_option( 'airc_settings', [] );
		return wp_parse_args( $settings, self::get_defaults() );
	}
}
