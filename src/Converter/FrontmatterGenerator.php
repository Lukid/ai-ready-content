<?php
/**
 * Generates YAML frontmatter for a post.
 */

namespace AIRC\Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

class FrontmatterGenerator {

	/**
	 * Generate a YAML frontmatter block for a post.
	 */
	public function generate( WP_Post $post ): string {
		$fields = [
			'title'     => $post->post_title,
			'date'      => get_the_date( 'c', $post ),
			'modified'  => get_the_modified_date( 'c', $post ),
			'author'    => get_the_author_meta( 'display_name', $post->post_author ),
			'excerpt'   => $this->get_excerpt( $post ),
			'url'       => get_permalink( $post ),
			'post_type' => $post->post_type,
		];

		$categories = $this->get_terms( $post, 'category' );
		if ( ! empty( $categories ) ) {
			$fields['categories'] = $categories;
		}

		$tags = $this->get_terms( $post, 'post_tag' );
		if ( ! empty( $tags ) ) {
			$fields['tags'] = $tags;
		}

		/**
		 * Filters the frontmatter fields before YAML serialization.
		 *
		 * @param array   $fields Key-value pairs for the frontmatter.
		 * @param WP_Post $post   The source post.
		 */
		$fields = apply_filters( 'airc_frontmatter_fields', $fields, $post );

		return $this->build_yaml( $fields );
	}

	private function get_excerpt( WP_Post $post ): string {
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '...' );
	}

	/**
	 * @return string[]
	 */
	private function get_terms( WP_Post $post, string $taxonomy ): array {
		$terms = get_the_terms( $post, $taxonomy );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		return array_map( fn( $term ) => $term->name, $terms );
	}

	private function build_yaml( array $fields ): string {
		$lines = [ '---' ];

		foreach ( $fields as $key => $value ) {
			if ( is_array( $value ) ) {
				$lines[] = $key . ':';
				foreach ( $value as $item ) {
					$lines[] = '  - ' . $this->escape_yaml( (string) $item );
				}
			} else {
				$lines[] = $key . ': ' . $this->escape_yaml( (string) $value );
			}
		}

		$lines[] = '---';
		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * Escape a YAML scalar value, quoting when necessary.
	 */
	private function escape_yaml( string $value ): string {
		if (
			'' === $value
			|| preg_match( '/[:#\[\]{}&*!|>\'"%@`,\?]/', $value )
			|| preg_match( '/^[\s-]/', $value )
			|| preg_match( '/\s$/', $value )
		) {
			return '"' . str_replace( [ '\\', '"' ], [ '\\\\', '\\"' ], $value ) . '"';
		}

		return $value;
	}
}
