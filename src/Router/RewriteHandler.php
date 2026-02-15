<?php
/**
 * Handles URL rewriting for .md endpoints and llms.txt.
 */

namespace AIRC\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RewriteHandler {

	public function __construct() {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'parse_request', [ $this, 'intercept_md_requests' ] );
	}

	/**
	 * Register rewrite rule for llms.txt.
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?airc_llms_txt=1', 'top' );
		add_rewrite_rule( '^airc-sitemap\.json$', 'index.php?airc_sitemap_json=1', 'top' );
	}

	/**
	 * Register custom query vars.
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'airc_markdown';
		$vars[] = 'airc_llms_txt';
		$vars[] = 'airc_sitemap_json';
		return $vars;
	}

	/**
	 * Intercept requests ending in .md and resolve them to posts.
	 */
	public function intercept_md_requests( \WP $wp ): void {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( empty( $path ) || ! str_ends_with( $path, '.md' ) ) {
			return;
		}

		// Strip .md suffix to get the original URL path.
		$original_path = substr( $path, 0, -3 );
		$original_path = rtrim( $original_path, '/' );

		// Try to resolve the URL to a post ID.
		$url     = home_url( $original_path );
		$post_id = url_to_postid( $url );

		// Also try with trailing slash.
		if ( ! $post_id ) {
			$post_id = url_to_postid( $url . '/' );
		}

		if ( $post_id ) {
			$wp->query_vars['airc_markdown'] = '1';
			$wp->query_vars['p']             = $post_id;
		}
	}
}
