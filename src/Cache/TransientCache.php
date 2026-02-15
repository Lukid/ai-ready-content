<?php
/**
 * Transient-based caching for markdown output.
 */

namespace AIRC\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Plugin;
use WP_Post;

class TransientCache {

	public function __construct() {
		add_action( 'save_post', [ $this, 'invalidate_post' ], 10, 2 );
		add_action( 'delete_post', [ $this, 'invalidate_post_by_id' ] );
		add_action( 'transition_post_status', [ $this, 'on_status_change' ], 10, 3 );
		add_action( 'set_object_terms', [ $this, 'on_terms_change' ], 10, 4 );
	}

	public function get_post_markdown( int $post_id ): ?string {
		$cached = get_transient( 'airc_md_' . $post_id );
		return false !== $cached ? $cached : null;
	}

	public function set_post_markdown( int $post_id, string $markdown ): void {
		$settings = Plugin::get_settings();
		$ttl      = (int) ( $settings['cache_ttl'] ?? 86400 );

		if ( $ttl > 0 ) {
			set_transient( 'airc_md_' . $post_id, $markdown, $ttl );
		}
	}

	public function get_llms_txt(): ?string {
		$cached = get_transient( 'airc_llms_txt' );
		return false !== $cached ? $cached : null;
	}

	public function set_llms_txt( string $content ): void {
		$settings = Plugin::get_settings();
		$ttl      = (int) ( $settings['cache_ttl'] ?? 86400 );

		if ( $ttl > 0 ) {
			set_transient( 'airc_llms_txt', $content, $ttl );
		}
	}

	public function get_sitemap_json(): ?string {
		$cached = get_transient( 'airc_sitemap_json' );
		return false !== $cached ? $cached : null;
	}

	public function set_sitemap_json( string $content ): void {
		$settings = Plugin::get_settings();
		$ttl      = (int) ( $settings['cache_ttl'] ?? 86400 );

		if ( $ttl > 0 ) {
			set_transient( 'airc_sitemap_json', $content, $ttl );
		}
	}

	/**
	 * Invalidate cache when a post is saved.
	 */
	public function invalidate_post( int $post_id, WP_Post $post = null ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		delete_transient( 'airc_md_' . $post_id );
		delete_transient( 'airc_llms_txt' );
		delete_transient( 'airc_sitemap_json' );
	}

	/**
	 * Invalidate cache when a post is deleted.
	 */
	public function invalidate_post_by_id( int $post_id ): void {
		delete_transient( 'airc_md_' . $post_id );
		delete_transient( 'airc_llms_txt' );
		delete_transient( 'airc_sitemap_json' );
	}

	/**
	 * Invalidate on post status transitions.
	 */
	public function on_status_change( string $new_status, string $old_status, WP_Post $post ): void {
		if ( $new_status !== $old_status ) {
			$this->invalidate_post( $post->ID, $post );
		}
	}

	/**
	 * Invalidate when taxonomy terms change.
	 */
	public function on_terms_change( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void {
		if ( wp_is_post_revision( $object_id ) ) {
			return;
		}

		delete_transient( 'airc_md_' . $object_id );
		delete_transient( 'airc_llms_txt' );
		delete_transient( 'airc_sitemap_json' );
	}

	/**
	 * Flush all plugin transients.
	 */
	public function flush_all(): void {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_airc_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_airc_' ) . '%'
			)
		);
	}
}
