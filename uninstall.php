<?php
/**
 * AI-Ready Content uninstall handler.
 *
 * Removes all plugin data when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'airc_settings' );

global $wpdb;

// Clean up all plugin transients.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_airc_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_airc_' ) . '%'
	)
);
