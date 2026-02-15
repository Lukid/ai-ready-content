<?php
/**
 * Serves the /airc-sitemap.json endpoint.
 */

namespace AIRC\Endpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Cache\TransientCache;
use AIRC\Helpers\PostTypeHelper;
use AIRC\Plugin;

class SitemapEndpoint {

	public function __construct(
		private PostTypeHelper $helper,
		private TransientCache $cache,
	) {
		add_action( 'template_redirect', [ $this, 'handle' ], 1 );
	}

	public function handle(): void {
		if ( empty( get_query_var( 'airc_sitemap_json' ) ) ) {
			return;
		}

		$output = $this->cache->get_sitemap_json();

		if ( null === $output ) {
			$output = $this->generate();
			$this->cache->set_sitemap_json( $output );
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'" );
		header( 'Cache-Control: public, max-age=3600' );

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON output.
		exit;
	}

	/**
	 * Generate the JSON sitemap content.
	 */
	private function generate(): string {
		$settings      = Plugin::get_settings();
		$enabled_types = $this->helper->get_enabled_post_types();
		$post_limit    = (int) ( $settings['llms_txt_post_limit'] ?? 100 );

		$entries = [];

		foreach ( $enabled_types as $post_type ) {
			$type_obj = get_post_type_object( $post_type );

			if ( ! $type_obj ) {
				continue;
			}

			$posts = get_posts(
				[
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => $post_limit,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'has_password'   => false,
				]
			);

			foreach ( $posts as $post ) {
				$permalink = get_permalink( $post );

				// Skip posts whose permalink is the site root (no valid .md URL).
				if ( untrailingslashit( $permalink ) === untrailingslashit( home_url() ) ) {
					continue;
				}

				$entries[] = [
					'title'         => $post->post_title,
					'url'           => rtrim( $permalink, '/' ) . '.md',
					'post_type'     => $post_type,
					'date_published' => get_the_date( 'c', $post ),
					'date_modified' => get_the_modified_date( 'c', $post ),
				];
			}
		}

		$data = [
			'generated' => gmdate( 'c' ),
			'site'      => get_bloginfo( 'name' ),
			'count'     => count( $entries ),
			'posts'     => $entries,
		];

		return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
