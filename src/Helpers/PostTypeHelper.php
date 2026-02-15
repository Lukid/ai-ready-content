<?php
/**
 * Post type eligibility helpers.
 */

namespace AIRC\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Plugin;
use WP_Post;

class PostTypeHelper {

	/**
	 * Get the list of post types enabled in settings.
	 *
	 * @return string[]
	 */
	public function get_enabled_post_types(): array {
		$settings = Plugin::get_settings();
		return (array) ( $settings['enabled_post_types'] ?? [ 'post', 'page' ] );
	}

	/**
	 * Get all public post types (excluding attachment).
	 *
	 * @return \WP_Post_Type[]
	 */
	public function get_public_post_types(): array {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		unset( $post_types['attachment'] );
		return $post_types;
	}

	/**
	 * Check whether a post is eligible for markdown conversion.
	 */
	public function is_post_eligible( WP_Post $post ): bool {
		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		if ( ! empty( $post->post_password ) ) {
			return false;
		}

		if ( ! in_array( $post->post_type, $this->get_enabled_post_types(), true ) ) {
			return false;
		}

		return true;
	}
}
