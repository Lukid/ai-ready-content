<?php
/**
 * Adds Llms-txt directive to robots.txt.
 */

namespace AIRC\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIRC\Plugin;

class RobotsTxt {

	public function __construct() {
		add_filter( 'robots_txt', [ $this, 'add_llms_directive' ], 100 );
	}

	/**
	 * Append Llms-txt directive to the virtual robots.txt.
	 */
	public function add_llms_directive( string $output ): string {
		$settings = Plugin::get_settings();

		if ( empty( $settings['enable_robots_txt'] ) ) {
			return $output;
		}

		if ( empty( $settings['enable_llms_txt'] ) ) {
			return $output;
		}

		// Only add directive if the site is public.
		if ( '1' !== get_option( 'blog_public', '1' ) ) {
			return $output;
		}

		$llms_url = home_url( '/llms.txt' );

		if ( false === strpos( $output, 'Llms-txt:' ) ) {
			$output .= "\nLlms-txt: " . esc_url( $llms_url ) . "\n";
		}

		return $output;
	}
}
