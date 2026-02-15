<?php
/**
 * Serves the /llms.txt endpoint.
 */

namespace AIRC\Endpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Cache\TransientCache;
use AIRC\Helpers\PostTypeHelper;
use AIRC\Plugin;

class LlmsTxtEndpoint {

	public function __construct(
		private PostTypeHelper $helper,
		private TransientCache $cache,
	) {
		add_action( 'template_redirect', [ $this, 'handle' ], 1 );
	}

	public function handle(): void {
		if ( empty( get_query_var( 'airc_llms_txt' ) ) ) {
			return;
		}

		$settings = Plugin::get_settings();

		if ( empty( $settings['enable_llms_txt'] ) ) {
			status_header( 404 );
			exit;
		}

		$output = $this->cache->get_llms_txt();

		if ( null === $output ) {
			$output = $this->generate();
			$this->cache->set_llms_txt( $output );
		}

		/**
		 * Filters the llms.txt output before sending.
		 *
		 * @param string $output The full llms.txt content.
		 */
		$output = apply_filters( 'airc_llms_txt_output', $output );

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'" );

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generate llms.txt content conforming to llmstxt.org spec.
	 */
	private function generate(): string {
		$settings    = Plugin::get_settings();
		$site_name   = get_bloginfo( 'name' );
		$description = get_bloginfo( 'description' );
		$post_limit  = (int) ( $settings['llms_txt_post_limit'] ?? 100 );

		$lines   = [];
		$lines[] = '# ' . $site_name;

		if ( ! empty( $description ) ) {
			$lines[] = '';
			$lines[] = '> ' . $description;
		}

		$enabled_types = $this->helper->get_enabled_post_types();

		foreach ( $enabled_types as $post_type ) {
			$type_obj = get_post_type_object( $post_type );

			if ( ! $type_obj ) {
				continue;
			}

			$query_args = [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $post_limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'has_password'   => false,
			];

			/**
			 * Filters the WP_Query args used to build llms.txt sections.
			 *
			 * @param array  $query_args WP_Query arguments.
			 * @param string $post_type  Current post type slug.
			 */
			$query_args = apply_filters( 'airc_llms_txt_post_query_args', $query_args, $post_type );

			$posts = get_posts( $query_args );

			if ( empty( $posts ) ) {
				continue;
			}

			$lines[] = '';
			$lines[] = '## ' . $type_obj->labels->name;
			$lines[] = '';

			foreach ( $posts as $post ) {
				$url     = rtrim( get_permalink( $post ), '/' ) . '.md';
				$title   = $post->post_title;
				$excerpt = wp_strip_all_tags( $post->post_excerpt );

				if ( empty( $excerpt ) ) {
					$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '...' );
				}

				$entry = '- [' . $title . '](' . $url . ')';

				if ( ! empty( $excerpt ) ) {
					$entry .= ': ' . $excerpt;
				}

				$lines[] = $entry;
			}
		}

		$lines[] = '';

		return implode( "\n", $lines );
	}
}
