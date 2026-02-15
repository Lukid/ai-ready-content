/**
 * AI-Ready Content - Meta box preview scripts.
 */
/* global jQuery, aircMetabox */
( function( $ ) {
	'use strict';

	$( function() {
		var $box = $( '.airc-metabox' );
		if ( ! $box.length ) {
			return;
		}

		var postId = $box.data( 'post-id' );
		var nonce  = $box.data( 'nonce' );

		// Load markdown preview.
		$( '#airc-load-preview' ).on( 'click', function() {
			var $btn      = $( this );
			var $textarea = $( '#airc-preview-content' );

			$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' );
			$textarea.val( aircMetabox.i18n.loading ).show();

			$.post( aircMetabox.ajaxurl, {
				action:  'airc_preview_markdown',
				post_id: postId,
				nonce:   nonce
			}, function( response ) {
				if ( response.success && response.data.markdown ) {
					$textarea.val( response.data.markdown );
				} else {
					$textarea.val( aircMetabox.i18n.noContent );
				}
				$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
			} ).fail( function() {
				$textarea.val( aircMetabox.i18n.error );
				$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
			} );
		} );

		// Invalidate single post cache.
		$( '#airc-invalidate-cache' ).on( 'click', function() {
			var $btn    = $( this );
			var $result = $( '#airc-cache-result' );
			var $status = $( '#airc-cache-status' );

			$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' );
			$result.text( aircMetabox.i18n.clearing );

			$.post( aircMetabox.ajaxurl, {
				action:  'airc_invalidate_post_cache',
				post_id: postId,
				nonce:   nonce
			}, function( response ) {
				if ( response.success ) {
					$result.text( response.data.message );
					$status.text( aircMetabox.i18n.notCached );
				} else {
					$result.text( aircMetabox.i18n.error );
				}
				$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
				setTimeout( function() {
					$result.text( '' );
				}, 3000 );
			} ).fail( function() {
				$result.text( aircMetabox.i18n.error );
				$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
			} );
		} );
	} );
} )( jQuery );
