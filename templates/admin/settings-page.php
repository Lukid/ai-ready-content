<?php
/**
 * Admin settings page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap airc-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form action="options.php" method="post">
		<?php
		settings_fields( 'airc_settings_group' );
		do_settings_sections( 'ai-ready-content' );
		submit_button();
		?>
	</form>

	<hr />

	<h2><?php esc_html_e( 'Cache', 'ai-ready-content' ); ?></h2>
	<p>
		<button type="button" id="airc-flush-cache" class="button button-secondary">
			<?php esc_html_e( 'Clear All Cache', 'ai-ready-content' ); ?>
		</button>
		<span id="airc-flush-result" role="status" aria-live="polite" aria-atomic="true"></span>
	</p>

	<script>
	jQuery( function( $ ) {
		$( '#airc-flush-cache' ).on( 'click', function() {
			var $btn = $( this );
			var $result = $( '#airc-flush-result' );
			$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' );
			$result.text( '<?php echo esc_js( __( 'Flushing cache…', 'ai-ready-content' ) ); ?>' );
			$.post( ajaxurl, {
				action: 'airc_flush_cache',
				nonce: '<?php echo esc_js( wp_create_nonce( 'airc_flush_cache' ) ); ?>'
			}, function( response ) {
				$result
					.text( response.data.message )
					.fadeIn()
					.delay( 3000 )
					.fadeOut();
				$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
			} ).fail( function() {
				$result
					.text( '<?php echo esc_js( __( 'Error clearing cache.', 'ai-ready-content' ) ); ?>' )
					.fadeIn();
				$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
			} );
		} );
	} );
	</script>

	<hr />

	<h2><?php esc_html_e( 'Quick Links', 'ai-ready-content' ); ?></h2>
	<ul>
		<li>
			<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View llms.txt', 'ai-ready-content' ); ?> &rarr;
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new window)', 'ai-ready-content' ); ?></span>
			</a>
		</li>
		<li>
			<a href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View llms-full.txt', 'ai-ready-content' ); ?> &rarr;
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new window)', 'ai-ready-content' ); ?></span>
			</a>
		</li>
	</ul>
</div>
