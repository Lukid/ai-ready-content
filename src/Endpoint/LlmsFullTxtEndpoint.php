<?php
/**
 * Serves the /llms-full.txt endpoint — comprehensive content index.
 */

namespace AIRC\Endpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Cache\TransientCache;
use AIRC\Helpers\PostTypeHelper;
use AIRC\Plugin;

class LlmsFullTxtEndpoint {

	public function __construct(
		private PostTypeHelper $helper,
		private TransientCache $cache,
	) {
		add_action( 'template_redirect', [ $this, 'handle' ], 1 );
	}

	public function handle(): void {
		if ( empty( get_query_var( 'airc_llms_full_txt' ) ) ) {
			return;
		}

		$settings = Plugin::get_settings();

		if ( empty( $settings['enable_llms_full_txt'] ) || empty( $settings['enable_llms_txt'] ) ) {
			status_header( 404 );
			exit;
		}

		$output = $this->cache->get_llms_full_txt();

		if ( null === $output ) {
			$output = $this->generate();
			$this->cache->set_llms_full_txt( $output );
		}

		/**
		 * Filters the llms-full.txt output before sending.
		 *
		 * @param string $output The full llms-full.txt content.
		 */
		$output = apply_filters( 'airc_llms_full_txt_output', $output );

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'" );

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generate llms-full.txt content — comprehensive listing.
	 */
	private function generate(): string {
		$settings    = Plugin::get_settings();
		$site_name   = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
		$description = html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES, 'UTF-8' );
		$post_limit  = (int) ( $settings['llms_full_txt_post_limit'] ?? $settings['llms_txt_post_limit'] ?? 100 );

		$lines   = [];
		$lines[] = '# ' . $site_name . " \xe2\x80\x94 Full Index";

		if ( ! empty( $description ) ) {
			$lines[] = '';
			$lines[] = '> ' . $description;
		}

		$lines[] = '';
		$lines[] = '> This is the comprehensive content index. For a curated summary, see [llms.txt](' . home_url( '/llms.txt' ) . ').';

		$enabled_types = $this->helper->get_enabled_post_types();

		$exclude_ids   = [];
		$front_page_id = (int) get_option( 'page_on_front' );
		if ( $front_page_id > 0 ) {
			$exclude_ids[] = $front_page_id;
		}
		$posts_page_id = (int) get_option( 'page_for_posts' );
		if ( $posts_page_id > 0 ) {
			$exclude_ids[] = $posts_page_id;
		}

		foreach ( $enabled_types as $post_type ) {
			$type_obj = get_post_type_object( $post_type );

			if ( ! $type_obj ) {
				continue;
			}

			$query_args = [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $post_limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'has_password'   => false,
			];

			if ( ! empty( $exclude_ids ) ) {
				$query_args['post__not_in'] = $exclude_ids;
			}

			/**
			 * Filters the WP_Query args used to build llms-full.txt sections.
			 *
			 * @param array  $query_args WP_Query arguments.
			 * @param string $post_type  Current post type slug.
			 */
			$query_args = apply_filters( 'airc_llms_full_txt_post_query_args', $query_args, $post_type );

			$posts = get_posts( $query_args );

			if ( empty( $posts ) ) {
				continue;
			}

			$lines[] = '';
			$lines[] = '## ' . $type_obj->labels->name;
			$lines[] = '';

			foreach ( $posts as $post ) {
				$permalink = get_permalink( $post );

				// Skip posts whose permalink is the site root (no valid .md URL).
				if ( untrailingslashit( $permalink ) === untrailingslashit( home_url() ) ) {
					continue;
				}

				$url     = rtrim( $permalink, '/' ) . '.md';
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
