<?php
/**
 * Meta box for markdown preview in the classic editor and Gutenberg sidebar panel.
 */

namespace AIRC\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Cache\TransientCache;
use AIRC\Converter\ContentPreparer;
use AIRC\Converter\FrontmatterGenerator;
use AIRC\Converter\MarkdownConverter;
use AIRC\Helpers\PostTypeHelper;

class PreviewMetaBox {

	public function __construct(
		private PostTypeHelper $helper,
		private TransientCache $cache,
		private ContentPreparer $preparer,
		private MarkdownConverter $converter,
		private FrontmatterGenerator $frontmatter,
	) {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_gutenberg_assets' ] );
		add_action( 'wp_ajax_airc_preview_markdown', [ $this, 'ajax_preview_markdown' ] );
		add_action( 'wp_ajax_airc_invalidate_post_cache', [ $this, 'ajax_invalidate_post_cache' ] );
		add_action( 'wp_ajax_airc_post_cache_status', [ $this, 'ajax_post_cache_status' ] );
	}

	/**
	 * Register meta box for all enabled post types.
	 */
	public function register_meta_box(): void {
		$enabled_types = $this->helper->get_enabled_post_types();

		foreach ( $enabled_types as $post_type ) {
			add_meta_box(
				'airc_preview',
				__( 'AI-Ready Content', 'ai-ready-content' ),
				[ $this, 'render_meta_box' ],
				$post_type,
				'normal',
				'low'
			);
		}
	}

	/**
	 * Render the meta box content.
	 *
	 * @param \WP_Post $post The current post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$permalink = get_permalink( $post );
		$md_url    = $permalink ? untrailingslashit( $permalink ) . '.md' : '';
		$cached    = $this->cache->get_post_markdown( $post->ID );
		$nonce     = wp_create_nonce( 'airc_preview_' . $post->ID );
		?>
		<div class="airc-metabox" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<strong><?php esc_html_e( 'Markdown URL:', 'ai-ready-content' ); ?></strong>
				<?php if ( 'publish' === $post->post_status && $md_url ) : ?>
					<a href="<?php echo esc_url( $md_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $md_url ); ?>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new window)', 'ai-ready-content' ); ?></span>
					</a>
				<?php else : ?>
					<em><?php esc_html_e( 'Publish the post to generate the markdown URL.', 'ai-ready-content' ); ?></em>
				<?php endif; ?>
			</p>

			<p>
				<strong><?php esc_html_e( 'Cache:', 'ai-ready-content' ); ?></strong>
				<span id="airc-cache-status">
					<?php if ( null !== $cached ) : ?>
						<?php esc_html_e( 'Cached', 'ai-ready-content' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Not cached', 'ai-ready-content' ); ?>
					<?php endif; ?>
				</span>
				<button type="button" id="airc-invalidate-cache" class="button button-small">
					<?php esc_html_e( 'Clear Cache', 'ai-ready-content' ); ?>
				</button>
				<span id="airc-cache-result" role="status" aria-live="polite" aria-atomic="true"></span>
			</p>

			<p>
				<button type="button" id="airc-load-preview" class="button button-secondary">
					<?php esc_html_e( 'Load Markdown Preview', 'ai-ready-content' ); ?>
				</button>
			</p>
			<textarea id="airc-preview-content" class="large-text" rows="12" readonly style="display:none;font-family:monospace;"></textarea>
		</div>
		<?php
	}

	/**
	 * Enqueue meta box JavaScript on post edit screens for enabled post types.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->helper->get_enabled_post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'airc-metabox',
			AIRC_PLUGIN_URL . 'assets/js/metabox.js',
			[ 'jquery' ],
			AIRC_VERSION,
			true
		);

		wp_localize_script( 'airc-metabox', 'aircMetabox', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => [
				'loading'    => __( 'Loading…', 'ai-ready-content' ),
				'clearing'   => __( 'Clearing…', 'ai-ready-content' ),
				'cleared'    => __( 'Cache cleared.', 'ai-ready-content' ),
				'notCached'  => __( 'Not cached', 'ai-ready-content' ),
				'cached'     => __( 'Cached', 'ai-ready-content' ),
				'error'      => __( 'Error occurred.', 'ai-ready-content' ),
				'noContent'  => __( 'No content to preview. Save or publish the post first.', 'ai-ready-content' ),
			],
		] );
	}

	/**
	 * Enqueue Gutenberg sidebar panel script for enabled post types.
	 */
	public function enqueue_gutenberg_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->helper->get_enabled_post_types(), true ) ) {
			return;
		}

		$post_id = 0;
		if ( isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
		}

		wp_enqueue_script(
			'airc-gutenberg-panel',
			AIRC_PLUGIN_URL . 'assets/js/gutenberg-panel.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ],
			AIRC_VERSION,
			true
		);

		wp_localize_script( 'airc-gutenberg-panel', 'aircGutenberg', [
			'ajaxurl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => $post_id ? wp_create_nonce( 'airc_preview_' . $post_id ) : '',
			'enabledPostTypes' => $this->helper->get_enabled_post_types(),
			'i18n'             => [
				'markdownUrl'  => __( 'Markdown URL:', 'ai-ready-content' ),
				'publishFirst' => __( 'Publish the post to generate the markdown URL.', 'ai-ready-content' ),
				'cache'        => __( 'Cache:', 'ai-ready-content' ),
				'cached'       => __( 'Cached', 'ai-ready-content' ),
				'notCached'    => __( 'Not cached', 'ai-ready-content' ),
				'clearCache'   => __( 'Clear Cache', 'ai-ready-content' ),
				'cleared'      => __( 'Cache cleared.', 'ai-ready-content' ),
				'error'        => __( 'Error occurred.', 'ai-ready-content' ),
			],
		] );
	}

	/**
	 * AJAX handler: generate and return markdown preview for a post.
	 */
	public function ajax_preview_markdown(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! check_ajax_referer( 'airc_preview_' . $post_id, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'ai-ready-content' ) ], 403 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'ai-ready-content' ) ], 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => __( 'Post not found.', 'ai-ready-content' ) ], 404 );
		}

		// Try cache first.
		$output = $this->cache->get_post_markdown( $post->ID );

		if ( null === $output ) {
			$html     = $this->preparer->prepare( $post );
			$markdown = $this->converter->convert( $html );
			$front    = $this->frontmatter->generate( $post );
			$output   = $front . $markdown;

			/** This filter is documented in src/Endpoint/PostEndpoint.php */
			$output = apply_filters( 'airc_markdown_output', $output, $post );
		}

		wp_send_json_success( [ 'markdown' => $output ] );
	}

	/**
	 * AJAX handler: invalidate cache for a single post.
	 */
	public function ajax_invalidate_post_cache(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! check_ajax_referer( 'airc_preview_' . $post_id, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'ai-ready-content' ) ], 403 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'ai-ready-content' ) ], 403 );
		}

		delete_transient( 'airc_md_' . $post_id );

		wp_send_json_success( [ 'message' => __( 'Cache cleared.', 'ai-ready-content' ) ] );
	}

	/**
	 * AJAX handler: return cache status for a post.
	 */
	public function ajax_post_cache_status(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! check_ajax_referer( 'airc_preview_' . $post_id, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'ai-ready-content' ) ], 403 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'ai-ready-content' ) ], 403 );
		}

		$cached = $this->cache->get_post_markdown( $post_id );

		wp_send_json_success( [
			'cached' => null !== $cached,
		] );
	}
}
