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
		<span id="airc-flush-result"></span>
	</p>

	<script>
	jQuery( function( $ ) {
		$( '#airc-flush-cache' ).on( 'click', function() {
			var $btn = $( this );
			$btn.prop( 'disabled', true );
			$.post( ajaxurl, {
				action: 'airc_flush_cache',
				nonce: '<?php echo esc_js( wp_create_nonce( 'airc_flush_cache' ) ); ?>'
			}, function( response ) {
				$( '#airc-flush-result' )
					.text( response.data.message )
					.fadeIn()
					.delay( 3000 )
					.fadeOut();
				$btn.prop( 'disabled', false );
			} ).fail( function() {
				$( '#airc-flush-result' )
					.text( '<?php echo esc_js( __( 'Error clearing cache.', 'ai-ready-content' ) ); ?>' )
					.fadeIn();
				$btn.prop( 'disabled', false );
			} );
		} );
	} );
	</script>

	<hr />

	<h2><?php esc_html_e( 'Quick Links', 'ai-ready-content' ); ?></h2>
	<ul>
		<li>
			<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank">
				<?php esc_html_e( 'View llms.txt', 'ai-ready-content' ); ?> &rarr;
			</a>
		</li>
	</ul>
</div>
