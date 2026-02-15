<?php
/**
 * Content negotiation: serves markdown when Accept: text/markdown is sent.
 */

namespace AIRC\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Endpoint\PostEndpoint;
use AIRC\Helpers\PostTypeHelper;
use AIRC\Plugin;
use WP_Post;

class ContentNegotiator {

	public function __construct(
		private PostEndpoint $post_endpoint,
		private PostTypeHelper $helper,
	) {
		add_action( 'template_redirect', [ $this, 'handle' ], 0 );
	}

	public function handle(): void {
		$settings = Plugin::get_settings();

		if ( empty( $settings['enable_content_negotiation'] ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

		if ( false === stripos( $accept, 'text/markdown' ) ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! $this->helper->is_post_eligible( $post ) ) {
			return;
		}

		$this->post_endpoint->serve_post( $post );
	}
}
