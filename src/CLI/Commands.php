<?php
/**
 * WP-CLI commands for AI-Ready Content.
 */

namespace AIRC\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Cache\TransientCache;
use AIRC\Converter\ContentPreparer;
use AIRC\Converter\FrontmatterGenerator;
use AIRC\Converter\MarkdownConverter;
use AIRC\Helpers\PostTypeHelper;
use AIRC\Plugin;
use WP_CLI;

class Commands {

	public function __construct(
		private PostTypeHelper $helper,
		private TransientCache $cache,
		private ContentPreparer $preparer,
		private MarkdownConverter $converter,
		private FrontmatterGenerator $frontmatter,
	) {}

	/**
	 * Flush the markdown cache.
	 *
	 * Clears all cached markdown transients, or a specific post/post type.
	 *
	 * ## OPTIONS
	 *
	 * [--post_id=<id>]
	 * : Flush cache for a specific post ID only.
	 *
	 * [--post_type=<type>]
	 * : Flush cache for all posts of a specific post type.
	 *
	 * ## EXAMPLES
	 *
	 *     wp airc flush
	 *     wp airc flush --post_id=42
	 *     wp airc flush --post_type=page
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function flush( array $args, array $assoc_args ): void {
		$post_id   = isset( $assoc_args['post_id'] ) ? (int) $assoc_args['post_id'] : 0;
		$post_type = $assoc_args['post_type'] ?? '';

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				WP_CLI::error( sprintf( 'Post %d not found.', $post_id ) );
			}

			delete_transient( 'airc_md_' . $post_id );
			delete_transient( 'airc_llms_txt' );
			WP_CLI::success( sprintf( 'Cache flushed for post %d.', $post_id ) );
			return;
		}

		if ( '' !== $post_type ) {
			$posts = get_posts(
				[
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				]
			);

			if ( empty( $posts ) ) {
				WP_CLI::warning( sprintf( 'No published posts found for post type "%s".', $post_type ) );
				return;
			}

			$count = 0;
			foreach ( $posts as $pid ) {
				delete_transient( 'airc_md_' . $pid );
				++$count;
			}
			delete_transient( 'airc_llms_txt' );
			WP_CLI::success( sprintf( 'Cache flushed for %d posts of type "%s".', $count, $post_type ) );
			return;
		}

		$this->cache->flush_all();
		WP_CLI::success( 'All cache flushed.' );
	}

	/**
	 * Pre-generate markdown for all enabled posts.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<type>]
	 * : Generate only for a specific post type.
	 *
	 * [--force]
	 * : Regenerate even if cache already exists.
	 *
	 * ## EXAMPLES
	 *
	 *     wp airc generate
	 *     wp airc generate --post_type=post
	 *     wp airc generate --force
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate( array $args, array $assoc_args ): void {
		$post_type = $assoc_args['post_type'] ?? '';
		$force     = isset( $assoc_args['force'] );

		$types = '' !== $post_type
			? [ $post_type ]
			: $this->helper->get_enabled_post_types();

		$posts = get_posts(
			[
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]
		);

		if ( empty( $posts ) ) {
			WP_CLI::warning( 'No published posts found.' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d posts to process.', count( $posts ) ) );

		$generated = 0;
		$skipped   = 0;

		foreach ( $posts as $post ) {
			if ( ! $force && null !== $this->cache->get_post_markdown( $post->ID ) ) {
				WP_CLI::log( sprintf( 'Skipping post %d (%s) — already cached.', $post->ID, $post->post_title ) );
				++$skipped;
				continue;
			}

			$html     = $this->preparer->prepare( $post );
			$markdown = $this->converter->convert( $html );
			$front    = $this->frontmatter->generate( $post );
			$output   = $front . $markdown;

			/** This filter is documented in src/Endpoint/PostEndpoint.php */
			$output = apply_filters( 'airc_markdown_output', $output, $post );

			$this->cache->set_post_markdown( $post->ID, $output );

			WP_CLI::log( sprintf( 'Generated markdown for post %d (%s).', $post->ID, $post->post_title ) );
			++$generated;
		}

		WP_CLI::success( sprintf( 'Done. Generated: %d, Skipped: %d.', $generated, $skipped ) );
	}

	/**
	 * Show AI-Ready Content status.
	 *
	 * Displays number of enabled posts, cache entries, and total cache size.
	 *
	 * ## EXAMPLES
	 *
	 *     wp airc status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		global $wpdb;

		$enabled_types = $this->helper->get_enabled_post_types();

		$post_count = 0;
		foreach ( $enabled_types as $type ) {
			$counts = wp_count_posts( $type );
			if ( isset( $counts->publish ) ) {
				$post_count += (int) $counts->publish;
			}
		}

		WP_CLI::log( sprintf( 'Enabled post types: %s', implode( ', ', $enabled_types ) ) );
		WP_CLI::log( sprintf( 'Published posts: %d', $post_count ) );

		// Count cache entries.
		$cache_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_airc_md_' ) . '%'
			)
		);

		WP_CLI::log( sprintf( 'Cache entries: %d', $cache_count ) );

		// Total cache size.
		$cache_size = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COALESCE(SUM(LENGTH(option_value)), 0) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_airc_md_' ) . '%'
			)
		);

		WP_CLI::log( sprintf( 'Total cache size: %s', size_format( $cache_size ) ) );

		$settings = Plugin::get_settings();
		$ttl      = (int) $settings['cache_ttl'];
		if ( 0 === $ttl ) {
			WP_CLI::log( 'Cache TTL: disabled' );
		} else {
			WP_CLI::log( sprintf( 'Cache TTL: %s', human_time_diff( 0, $ttl ) ) );
		}

		WP_CLI::success( 'Status complete.' );
	}
}
