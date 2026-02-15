/**
 * AI-Ready Content - Gutenberg sidebar panel.
 *
 * Registers a PluginDocumentSettingPanel in the block editor
 * showing markdown URL, cache status, and cache invalidation.
 */
/* global wp, aircGutenberg */
( function() {
	'use strict';

	var el              = wp.element.createElement;
	var Fragment        = wp.element.Fragment;
	var useState        = wp.element.useState;
	var useEffect       = wp.element.useEffect;
	var useSelect       = wp.data.useSelect;
	var registerPlugin  = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var Button          = wp.components.Button;
	var ExternalLink    = wp.components.ExternalLink;
	var Spinner         = wp.components.Spinner;

	/**
	 * Build the .md URL from the post permalink.
	 */
	function getMdUrl( permalink ) {
		if ( ! permalink ) {
			return '';
		}
		return permalink.replace( /\/$/, '' ) + '.md';
	}

	/**
	 * Main panel component.
	 */
	function AircPanel() {
		var postType  = useSelect( function( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );
		var postId    = useSelect( function( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );
		var permalink = useSelect( function( select ) {
			return select( 'core/editor' ).getPermalink();
		}, [] );
		var postStatus = useSelect( function( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'status' );
		}, [] );

		var cacheState    = useState( null );
		var isCached      = cacheState[0];
		var setIsCached   = cacheState[1];

		var loadingState    = useState( false );
		var isLoading       = loadingState[0];
		var setIsLoading    = loadingState[1];

		var clearingState   = useState( false );
		var isClearing      = clearingState[0];
		var setIsClearing   = clearingState[1];

		var messageState    = useState( '' );
		var message         = messageState[0];
		var setMessage      = messageState[1];

		// Only render for enabled post types.
		if ( ! postType || aircGutenberg.enabledPostTypes.indexOf( postType ) === -1 ) {
			return null;
		}

		var mdUrl = getMdUrl( permalink );

		// Fetch cache status on mount.
		useEffect( function() {
			if ( ! postId ) {
				return;
			}
			fetchCacheStatus();
		}, [ postId ] ); // eslint-disable-line react-hooks/exhaustive-deps

		function fetchCacheStatus() {
			setIsLoading( true );
			var data = new FormData();
			data.append( 'action', 'airc_post_cache_status' );
			data.append( 'post_id', postId );
			data.append( 'nonce', aircGutenberg.nonce );

			fetch( aircGutenberg.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			} )
				.then( function( res ) { return res.json(); } )
				.then( function( res ) {
					if ( res.success ) {
						setIsCached( res.data.cached );
					}
					setIsLoading( false );
				} )
				.catch( function() {
					setIsLoading( false );
				} );
		}

		function handleClearCache() {
			setIsClearing( true );
			setMessage( '' );
			var data = new FormData();
			data.append( 'action', 'airc_invalidate_post_cache' );
			data.append( 'post_id', postId );
			data.append( 'nonce', aircGutenberg.nonce );

			fetch( aircGutenberg.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
			} )
				.then( function( res ) { return res.json(); } )
				.then( function( res ) {
					if ( res.success ) {
						setIsCached( false );
						setMessage( aircGutenberg.i18n.cleared );
					} else {
						setMessage( aircGutenberg.i18n.error );
					}
					setIsClearing( false );
					setTimeout( function() { setMessage( '' ); }, 3000 );
				} )
				.catch( function() {
					setMessage( aircGutenberg.i18n.error );
					setIsClearing( false );
				} );
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'airc-preview-panel',
				title: 'AI-Ready Content',
				icon: 'media-text',
			},
			// Markdown URL.
			el( 'div', { style: { marginBottom: '12px' } },
				el( 'strong', null, aircGutenberg.i18n.markdownUrl ),
				postStatus === 'publish' && mdUrl
					? el( 'div', { style: { marginTop: '4px', wordBreak: 'break-all' } },
						el( ExternalLink, { href: mdUrl }, mdUrl )
					)
					: el( 'em', { style: { display: 'block', marginTop: '4px' } },
						aircGutenberg.i18n.publishFirst
					)
			),
			// Cache status.
			el( 'div', { style: { marginBottom: '12px' } },
				el( 'strong', null, aircGutenberg.i18n.cache ),
				' ',
				isLoading
					? el( Spinner, null )
					: el( 'span', null,
						isCached
							? aircGutenberg.i18n.cached
							: aircGutenberg.i18n.notCached
					)
			),
			// Clear cache button.
			el( Button, {
				variant: 'secondary',
				isBusy: isClearing,
				disabled: isClearing,
				onClick: handleClearCache,
				'aria-busy': isClearing ? 'true' : undefined,
			}, aircGutenberg.i18n.clearCache ),
			// Status message.
			message
				? el( 'p', {
					role: 'status',
					'aria-live': 'polite',
					style: { marginTop: '8px' },
				}, message )
				: null
		);
	}

	registerPlugin( 'airc-preview', {
		render: AircPanel,
		icon: 'media-text',
	} );
} )();
