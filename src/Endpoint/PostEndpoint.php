<?php
/**
 * Serves individual posts as markdown via .md URLs.
 */

namespace AIRC\Endpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Cache\TransientCache;
use AIRC\Converter\ContentPreparer;
use AIRC\Converter\FrontmatterGenerator;
use AIRC\Converter\MarkdownConverter;
use AIRC\Helpers\PostTypeHelper;
use WP_Post;

class PostEndpoint {

	public function __construct(
		private ContentPreparer $preparer,
		private MarkdownConverter $converter,
		private FrontmatterGenerator $frontmatter,
		private TransientCache $cache,
		private PostTypeHelper $helper,
	) {
		add_action( 'template_redirect', [ $this, 'handle' ], 1 );
	}

	/**
	 * Handle markdown query var requests.
	 */
	public function handle(): void {
		if ( empty( get_query_var( 'airc_markdown' ) ) ) {
			return;
		}

		$post_id = (int) get_query_var( 'p' );

		if ( ! $post_id ) {
			$this->send_404();
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post || ! $this->helper->is_post_eligible( $post ) ) {
			$this->send_404();
			return;
		}

		$this->serve_post( $post );
	}

	/**
	 * Build and send the markdown response for a post.
	 */
	public function serve_post( WP_Post $post ): void {
		$output = $this->cache->get_post_markdown( $post->ID );

		if ( null === $output ) {
			$html     = $this->preparer->prepare( $post );
			$markdown = $this->converter->convert( $html );
			$front    = $this->frontmatter->generate( $post );
			$output   = $front . $markdown;

			/**
			 * Filters the final markdown output (frontmatter + content).
			 *
			 * @param string  $output Full markdown string.
			 * @param WP_Post $post   The source post.
			 */
			$output = apply_filters( 'airc_markdown_output', $output, $post );

			$this->cache->set_post_markdown( $post->ID, $output );
		}

		$headers = [
			'Content-Type'            => 'text/markdown; charset=utf-8',
			'X-Robots-Tag'            => 'noindex',
			'X-Content-Type-Options'  => 'nosniff',
			'Content-Security-Policy' => "default-src 'none'",
			'Last-Modified'           => gmdate( 'D, d M Y H:i:s', strtotime( $post->post_modified_gmt ) ) . ' GMT',
			'Cache-Control'           => 'public, max-age=3600',
		];

		/**
		 * Filters the HTTP response headers for markdown responses.
		 *
		 * @param array   $headers HTTP headers.
		 * @param WP_Post $post    The source post.
		 */
		$headers = apply_filters( 'airc_response_headers', $headers, $post );

		foreach ( $headers as $name => $value ) {
			// Validate header name: only alphanumeric and hyphens allowed.
			if ( ! preg_match( '/^[a-zA-Z0-9\-]+$/', $name ) ) {
				continue;
			}

			// Validate header value: no CR or LF (prevents header injection).
			if ( preg_match( '/[\r\n]/', $value ) ) {
				continue;
			}

			header( $name . ': ' . $value );
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw markdown output.
		exit;
	}

	private function send_404(): void {
		status_header( 404 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		echo "# 404 Not Found\n";
		exit;
	}
}
