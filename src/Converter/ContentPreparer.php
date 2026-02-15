<?php
/**
 * Prepares post HTML content for markdown conversion.
 */

namespace AIRC\Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

class ContentPreparer {

	/**
	 * Guard flag to prevent re-entrant calls via the_content filter.
	 */
	private static bool $is_converting = false;

	/**
	 * Prepare a post's content as clean HTML ready for conversion.
	 */
	public function prepare( WP_Post $post ): string {
		if ( self::$is_converting ) {
			return $post->post_content;
		}

		self::$is_converting = true;

		$content = $post->post_content;

		// Apply the_content filters for full rendering (Gutenberg blocks, shortcodes, embeds).
		$content = apply_filters( 'the_content', $content );

		/**
		 * Filters whether residual shortcodes should be stripped from the content.
		 *
		 * @param bool $strip Whether to strip residual shortcodes. Default true.
		 */
		if ( apply_filters( 'airc_strip_residual_shortcodes', true ) ) {
			$content = $this->strip_residual_shortcodes( $content );
		}

		$content = $this->clean_html( $content );

		self::$is_converting = false;

		/**
		 * Filters the prepared HTML before markdown conversion.
		 *
		 * @param string  $content Cleaned HTML.
		 * @param WP_Post $post    The source post.
		 */
		return apply_filters( 'airc_prepared_html', $content, $post );
	}

	/**
	 * Remove residual shortcodes that were not resolved by the_content filters.
	 *
	 * Strips both self-closing [shortcode ...] and enclosing [shortcode ...]...[/shortcode] patterns.
	 */
	private function strip_residual_shortcodes( string $content ): string {
		// Remove enclosing shortcodes: [tag ...]...[/tag].
		$content = preg_replace( '/\[(\w+)\b[^\]]*\].*?\[\/\1\]/s', '', $content );

		// Remove self-closing / opening shortcodes: [tag ...].
		$content = preg_replace( '/\[\w+\b[^\]]*\]/', '', $content );

		return $content;
	}

	/**
	 * Remove scripts, styles, empty paragraphs, and WP-specific cruft.
	 */
	private function clean_html( string $html ): string {
		// Remove script and style tags with their content.
		$html = preg_replace( '/<script\b[^>]*>.*?<\/script>/si', '', $html );
		$html = preg_replace( '/<style\b[^>]*>.*?<\/style>/si', '', $html );

		// Remove empty paragraphs.
		$html = preg_replace( '/<p>\s*<\/p>/i', '', $html );

		// Remove WordPress-specific CSS classes from tags but keep the tags.
		$html = preg_replace( '/\s+class="[^"]*"/i', '', $html );

		// Clean up excessive whitespace.
		$html = preg_replace( '/\n{3,}/', "\n\n", $html );

		return trim( $html );
	}
}
