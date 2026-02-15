<?php
/**
 * Admin settings page under Settings > AI-Ready Content.
 */

namespace AIRC\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Cache\TransientCache;
use AIRC\Helpers\PostTypeHelper;
use AIRC\Plugin;

class SettingsPage {

	private const OPTION_NAME = 'airc_settings';
	private const MENU_SLUG   = 'ai-ready-content';

	public function __construct(
		private PostTypeHelper $helper,
		private TransientCache $cache,
	) {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_airc_flush_cache', [ $this, 'ajax_flush_cache' ] );
	}

	public function add_menu_page(): void {
		add_options_page(
			__( 'AI-Ready Content', 'ai-ready-content' ),
			__( 'AI-Ready Content', 'ai-ready-content' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			'airc_settings_group',
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => Plugin::get_defaults(),
			]
		);

		add_settings_section(
			'airc_general',
			__( 'General Settings', 'ai-ready-content' ),
			'__return_null',
			self::MENU_SLUG
		);

		add_settings_field(
			'enabled_post_types',
			__( 'Enabled Post Types', 'ai-ready-content' ),
			[ $this, 'render_post_types_field' ],
			self::MENU_SLUG,
			'airc_general'
		);

		add_settings_field(
			'feature_toggles',
			__( 'Features', 'ai-ready-content' ),
			[ $this, 'render_feature_toggles_field' ],
			self::MENU_SLUG,
			'airc_general'
		);

		add_settings_field(
			'cache_ttl',
			__( 'Cache TTL', 'ai-ready-content' ),
			[ $this, 'render_cache_ttl_field' ],
			self::MENU_SLUG,
			'airc_general'
		);

		add_settings_field(
			'llms_txt_post_limit',
			__( 'llms.txt Post Limit', 'ai-ready-content' ),
			[ $this, 'render_post_limit_field' ],
			self::MENU_SLUG,
			'airc_general'
		);

		add_settings_field(
			'show_protected_teaser',
			__( 'Protected posts', 'ai-ready-content' ),
			[ $this, 'render_protected_teaser_field' ],
			self::MENU_SLUG,
			'airc_general'
		);

		add_settings_field(
			'image_handling',
			__( 'Image handling', 'ai-ready-content' ),
			[ $this, 'render_image_handling_field' ],
			self::MENU_SLUG,
			'airc_general'
		);

		add_settings_field(
			'frontmatter_meta_keys',
			__( 'Meta keys for frontmatter', 'ai-ready-content' ),
			[ $this, 'render_frontmatter_meta_keys_field' ],
			self::MENU_SLUG,
			'airc_general'
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		include AIRC_PLUGIN_DIR . 'templates/admin/settings-page.php';
	}

	public function render_post_types_field(): void {
		$settings   = Plugin::get_settings();
		$enabled    = (array) $settings['enabled_post_types'];
		$post_types = $this->helper->get_public_post_types();

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Enabled Post Types', 'ai-ready-content' ) . '</legend>';
		foreach ( $post_types as $slug => $type_obj ) {
			printf(
				'<label><input type="checkbox" name="%s[enabled_post_types][]" value="%s" %s /> %s</label><br />',
				esc_attr( self::OPTION_NAME ),
				esc_attr( $slug ),
				checked( in_array( $slug, $enabled, true ), true, false ),
				esc_html( $type_obj->labels->name )
			);
		}
		echo '</fieldset>';
	}

	public function render_feature_toggles_field(): void {
		$settings = Plugin::get_settings();
		$toggles  = [
			'enable_content_negotiation' => __( 'Content Negotiation', 'ai-ready-content' ),
			'enable_llms_txt'            => __( 'llms.txt endpoint', 'ai-ready-content' ),
			'enable_alternate_links'     => __( 'Alternate link headers', 'ai-ready-content' ),
			'enable_robots_txt'          => __( 'Robots.txt integration', 'ai-ready-content' ),
		];

		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Features', 'ai-ready-content' ) . '</legend>';
		foreach ( $toggles as $field => $label ) {
			$value = ! empty( $settings[ $field ] );
			printf(
				'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label><br />',
				esc_attr( self::OPTION_NAME ),
				esc_attr( $field ),
				checked( $value, true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
	}

	public function render_cache_ttl_field(): void {
		$settings = Plugin::get_settings();
		$value    = (int) $settings['cache_ttl'];
		$hours    = $value / 3600;

		printf(
			'<input type="number" name="%s[cache_ttl]" value="%s" min="0" max="168" step="1" class="small-text" aria-describedby="airc-cache-ttl-desc" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( (string) $hours )
		);
		printf(
			'<p id="airc-cache-ttl-desc" class="description">%s</p>',
			esc_html__( 'hours (0 = disabled)', 'ai-ready-content' )
		);
	}

	public function render_post_limit_field(): void {
		$settings = Plugin::get_settings();
		$value    = (int) $settings['llms_txt_post_limit'];

		printf(
			'<input type="number" name="%s[llms_txt_post_limit]" value="%s" min="1" max="500" step="1" class="small-text" aria-describedby="airc-post-limit-desc" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( (string) $value )
		);
		printf(
			'<p id="airc-post-limit-desc" class="description">%s</p>',
			esc_html__( 'posts per type', 'ai-ready-content' )
		);
	}

	public function render_protected_teaser_field(): void {
		$settings = Plugin::get_settings();
		$value    = ! empty( $settings['show_protected_teaser'] );

		printf(
			'<label><input type="checkbox" name="%s[show_protected_teaser]" value="1" %s /> %s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $value, true, false ),
			esc_html__( 'Show teaser for protected posts', 'ai-ready-content' )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'When enabled, password-protected posts return frontmatter and a placeholder message instead of 404.', 'ai-ready-content' )
		);
	}

	public function render_image_handling_field(): void {
		$settings = Plugin::get_settings();
		$value    = $settings['image_handling'];
		$options  = [
			'keep'     => __( 'Keep with alt text', 'ai-ready-content' ),
			'alt_only' => __( 'Alt text only', 'ai-ready-content' ),
			'remove'   => __( 'Remove', 'ai-ready-content' ),
		];

		printf(
			'<select name="%s[image_handling]" aria-describedby="airc-image-handling-desc">',
			esc_attr( self::OPTION_NAME )
		);
		foreach ( $options as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		printf(
			'<p id="airc-image-handling-desc" class="description">%s</p>',
			esc_html__( 'How images are handled in the markdown output.', 'ai-ready-content' )
		);
	}

	public function render_frontmatter_meta_keys_field(): void {
		$settings = Plugin::get_settings();
		$value    = $settings['frontmatter_meta_keys'];

		printf(
			'<textarea name="%s[frontmatter_meta_keys]" rows="4" cols="50" class="large-text code" aria-describedby="airc-meta-keys-desc">%s</textarea>',
			esc_attr( self::OPTION_NAME ),
			esc_textarea( $value )
		);
		printf(
			'<p id="airc-meta-keys-desc" class="description">%s</p>',
			esc_html__( 'One meta key per line. Values from these custom fields will be included in the YAML frontmatter.', 'ai-ready-content' )
		);
	}

	/**
	 * Sanitize and validate all settings input.
	 */
	public function sanitize_settings( $input ): array {
		$defaults  = Plugin::get_defaults();
		$sanitized = [];

		// Enabled post types: validate against actual public types.
		$sanitized['enabled_post_types'] = [];
		if ( ! empty( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] ) ) {
			$public_types = array_keys( $this->helper->get_public_post_types() );
			$sanitized['enabled_post_types'] = array_values(
				array_intersect( $input['enabled_post_types'], $public_types )
			);
		}

		// Checkboxes.
		$sanitized['enable_content_negotiation'] = ! empty( $input['enable_content_negotiation'] );
		$sanitized['enable_llms_txt']            = ! empty( $input['enable_llms_txt'] );
		$sanitized['enable_alternate_links']     = ! empty( $input['enable_alternate_links'] );
		$sanitized['enable_robots_txt']          = ! empty( $input['enable_robots_txt'] );

		// Cache TTL: input in hours, stored in seconds.
		$hours                  = isset( $input['cache_ttl'] ) ? absint( $input['cache_ttl'] ) : 24;
		$sanitized['cache_ttl'] = min( $hours, 168 ) * 3600;

		// Post limit.
		$sanitized['llms_txt_post_limit'] = isset( $input['llms_txt_post_limit'] )
			? min( absint( $input['llms_txt_post_limit'] ), 500 )
			: $defaults['llms_txt_post_limit'];

		// Protected teaser.
		$sanitized['show_protected_teaser'] = ! empty( $input['show_protected_teaser'] );

		// Image handling.
		$valid_modes                  = [ 'keep', 'alt_only', 'remove' ];
		$sanitized['image_handling'] = isset( $input['image_handling'] ) && in_array( $input['image_handling'], $valid_modes, true )
			? $input['image_handling']
			: $defaults['image_handling'];

		// Frontmatter meta keys: one key per line, trimmed, valid meta key characters only.
		$sanitized['frontmatter_meta_keys'] = '';
		if ( ! empty( $input['frontmatter_meta_keys'] ) ) {
			$lines = explode( "\n", $input['frontmatter_meta_keys'] );
			$valid = [];
			foreach ( $lines as $line ) {
				$key = sanitize_key( trim( $line ) );
				if ( '' !== $key ) {
					$valid[] = $key;
				}
			}
			$sanitized['frontmatter_meta_keys'] = implode( "\n", $valid );
		}

		// Flush cache on settings save.
		$this->cache->flush_all();

		return $sanitized;
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'airc-admin',
			AIRC_PLUGIN_URL . 'assets/css/admin.css',
			[],
			AIRC_VERSION
		);
	}

	/**
	 * AJAX handler to flush all cache.
	 */
	public function ajax_flush_cache(): void {
		check_ajax_referer( 'airc_flush_cache', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'ai-ready-content' ) ], 403 );
		}

		// Rate limiting: 10-second cooldown between flush requests.
		if ( get_transient( 'airc_flush_cooldown' ) ) {
			wp_send_json_error( [ 'message' => __( 'Please wait before flushing again.', 'ai-ready-content' ) ], 429 );
		}

		$this->cache->flush_all();
		set_transient( 'airc_flush_cooldown', 1, 10 );

		wp_send_json_success( [ 'message' => __( 'Cache cleared successfully.', 'ai-ready-content' ) ] );
	}
}
