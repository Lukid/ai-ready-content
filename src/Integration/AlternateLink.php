<?php
/**
 * Outputs <link rel="alternate"> tags for markdown versions.
 */

namespace AIRC\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Helpers\PostTypeHelper;
use AIRC\Plugin;
use WP_Post;

class AlternateLink {

	public function __construct(
		private PostTypeHelper $helper,
	) {
		add_action( 'wp_head', [ $this, 'output_link' ] );
	}

	public function output_link(): void {
		$settings = Plugin::get_settings();

		if ( empty( $settings['enable_alternate_links'] ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! $this->helper->is_post_eligible( $post ) ) {
			return;
		}

		$md_url = rtrim( get_permalink( $post ), '/' ) . '.md';

		printf(
			'<link rel="alternate" type="text/markdown" href="%s" />' . "\n",
			esc_url( $md_url )
		);
	}
}
