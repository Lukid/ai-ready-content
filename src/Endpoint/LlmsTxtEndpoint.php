<?php
/**
 * Serves the /llms.txt endpoint — curated content index.
 */

namespace AIRC\Endpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Cache\TransientCache;
use AIRC\Helpers\PostTypeHelper;
use AIRC\Plugin;
use WP_Post;

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
	 * Generate curated llms.txt content conforming to llmstxt.org spec.
	 */
	private function generate(): string {
		$settings       = Plugin::get_settings();
		$site_name      = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
		$description    = html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES, 'UTF-8' );
		$curated_limit  = (int) ( $settings['llms_txt_curated_limit'] ?? 10 );
		$optional_limit = (int) ( $settings['llms_txt_optional_limit'] ?? 10 );
		$show_taxes     = ! empty( $settings['llms_txt_show_taxonomies'] );

		$lines   = [];
		$lines[] = '# ' . $this->escape_markdown( $site_name );

		if ( ! empty( $description ) ) {
			$lines[] = '';
			$lines[] = '> ' . $this->escape_markdown( $description );
		}

		$enabled_types  = $this->helper->get_enabled_post_types();
		$optional_lines = [];

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

			$total_needed = $curated_limit + $optional_limit;

			$query_args = [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $total_needed,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'has_password'   => false,
			];

			if ( ! empty( $exclude_ids ) ) {
				$query_args['post__not_in'] = $exclude_ids;
			}

			/**
			 * Filters the WP_Query args used to build llms.txt sections.
			 *
			 * @param array  $query_args WP_Query arguments.
			 * @param string $post_type  Current post type slug.
			 */
			$query_args = apply_filters( 'airc_llms_txt_post_query_args', $query_args, $post_type );

			$posts = $this->query_with_sticky_priority( $query_args, $post_type, $total_needed );

			if ( empty( $posts ) ) {
				continue;
			}

			$curated_posts  = array_slice( $posts, 0, $curated_limit );
			$optional_posts = array_slice( $posts, $curated_limit, $optional_limit );

			$lines[] = '';
			$lines[] = '## ' . $type_obj->labels->name;
			$lines[] = '';

			foreach ( $curated_posts as $post ) {
				$entry = $this->format_entry( $post );
				if ( null !== $entry ) {
					$lines[] = $entry;
				}
			}

			foreach ( $optional_posts as $post ) {
				$entry = $this->format_entry( $post );
				if ( null !== $entry ) {
					$optional_lines[] = $entry;
				}
			}
		}

		// Taxonomy sections.
		if ( $show_taxes ) {
			$tax_lines = $this->generate_taxonomy_sections();
			if ( ! empty( $tax_lines ) ) {
				$lines = array_merge( $lines, $tax_lines );
			}
		}

		// ## Optional section per spec.
		if ( ! empty( $optional_lines ) ) {
			$lines[] = '';
			$lines[] = '## Optional';
			$lines[] = '';
			$lines   = array_merge( $lines, $optional_lines );
		}

		// Link to llms-full.txt if enabled.
		if ( ! empty( $settings['enable_llms_full_txt'] ) ) {
			$lines[] = '';
			$lines[] = '---';
			$lines[] = '';
			$lines[] = 'See also: [Full content index](' . home_url( '/llms-full.txt' ) . ')';
		}

		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * Query posts with sticky posts prioritized first (for 'post' type only).
	 *
	 * @param array  $query_args Base WP_Query arguments.
	 * @param string $post_type  Post type slug.
	 * @param int    $total      Total posts needed.
	 * @return WP_Post[]
	 */
	private function query_with_sticky_priority( array $query_args, string $post_type, int $total ): array {
		$posts      = [];
		$sticky_ids = ( 'post' === $post_type ) ? get_option( 'sticky_posts', [] ) : [];

		if ( ! empty( $sticky_ids ) ) {
			$sticky_args                   = $query_args;
			$sticky_args['post__in']       = $sticky_ids;
			$sticky_args['posts_per_page'] = $total;
			$sticky_args['orderby']        = 'modified';
			$sticky_args['order']          = 'DESC';
			$posts                         = get_posts( $sticky_args );
		}

		$remaining = $total - count( $posts );

		if ( $remaining > 0 ) {
			$rest_args                   = $query_args;
			$rest_args['posts_per_page'] = $remaining;
			$rest_args['orderby']        = 'modified';
			$rest_args['order']          = 'DESC';

			if ( ! empty( $posts ) ) {
				$rest_args['post__not_in'] = wp_list_pluck( $posts, 'ID' );
			}

			$rest_posts = get_posts( $rest_args );
			$posts      = array_merge( $posts, $rest_posts );
		}

		return $posts;
	}

	/**
	 * Format a single post entry for llms.txt.
	 */
	private function format_entry( WP_Post $post ): ?string {
		$permalink = get_permalink( $post );

		// Skip posts whose permalink is the site root (no valid .md URL).
		if ( untrailingslashit( $permalink ) === untrailingslashit( home_url() ) ) {
			return null;
		}

		$url     = rtrim( $permalink, '/' ) . '.md';
		$title   = $this->escape_markdown( $post->post_title );
		$excerpt = wp_strip_all_tags( $post->post_excerpt );

		if ( empty( $excerpt ) ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '...' );
		}

		$excerpt = $this->escape_markdown( $excerpt );

		$entry = '- [' . $title . '](' . $url . ')';

		if ( ! empty( $excerpt ) ) {
			$entry .= ': ' . $excerpt;
		}

		return $entry;
	}

	/**
	 * Escape markdown special characters that could break link syntax.
	 */
	private function escape_markdown( string $text ): string {
		return str_replace(
			[ '[', ']', '(', ')' ],
			[ '\[', '\]', '\(', '\)' ],
			$text
		);
	}

	/**
	 * Generate taxonomy sections (top categories and tags by post count).
	 *
	 * @return string[]
	 */
	private function generate_taxonomy_sections(): array {
		$lines      = [];
		$taxonomies = [ 'category', 'post_tag' ];

		foreach ( $taxonomies as $taxonomy ) {
			$tax_obj = get_taxonomy( $taxonomy );

			if ( ! $tax_obj ) {
				continue;
			}

			$terms = get_terms( [
				'taxonomy'   => $taxonomy,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 5,
				'hide_empty' => true,
			] );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$lines[] = '';
			$lines[] = '## ' . $tax_obj->labels->name;
			$lines[] = '';

			foreach ( $terms as $term ) {
				$term_link = get_term_link( $term );

				if ( is_wp_error( $term_link ) ) {
					continue;
				}

				$lines[] = '- [' . $this->escape_markdown( $term->name ) . '](' . $term_link . '): '
					. sprintf(
						/* translators: %d: number of posts */
						_n( '%d post', '%d posts', $term->count, 'ai-ready-content' ),
						$term->count
					);
			}
		}

		return $lines;
	}
}
