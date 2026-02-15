<?php
/**
 * Converts HTML to Markdown using league/html-to-markdown.
 */

namespace AIRC\Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use League\HTMLToMarkdown\HtmlConverter;

class MarkdownConverter {

	/**
	 * Convert an HTML string to Markdown.
	 */
	public function convert( string $html ): string {
		if ( empty( trim( $html ) ) ) {
			return '';
		}

		$options = [
			'header_style'    => 'atx',
			'strip_tags'      => true,
			'remove_nodes'    => 'script style nav footer',
			'hard_break'      => false,
			'list_item_style' => '-',
		];

		/**
		 * Filters the HTMLToMarkdown converter options.
		 *
		 * @param array $options Converter options.
		 */
		$options = apply_filters( 'airc_converter_options', $options );

		$converter = new HtmlConverter( $options );
		$markdown  = $converter->convert( $html );

		$markdown = $this->post_process( $markdown );

		/**
		 * Filters the converted markdown.
		 *
		 * @param string $markdown Converted markdown.
		 */
		return apply_filters( 'airc_converted_markdown', $markdown );
	}

	/**
	 * Clean up converter output.
	 */
	private function post_process( string $markdown ): string {
		// Fix excessive blank lines.
		$markdown = preg_replace( '/\n{3,}/', "\n\n", $markdown );

		// Remove trailing whitespace from lines.
		$markdown = preg_replace( '/[ \t]+$/m', '', $markdown );

		// Ensure single trailing newline.
		$markdown = rtrim( $markdown ) . "\n";

		return $markdown;
	}
}
