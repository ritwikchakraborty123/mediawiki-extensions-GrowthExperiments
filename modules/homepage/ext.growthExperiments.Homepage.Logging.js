( function () {
	var Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		handleHover = function ( action ) {
			return function () {
				var $module = $( this ),
					moduleName = $module.data( 'module-name' ),
					mode = $module.data( 'mode' );
				logger.log( moduleName, mode, 'hover-' + action );
			};
		},
		moduleSelector = '.growthexperiments-homepage-module',
		$modules = $( moduleSelector ),
		handleClick = function ( e ) {
			var $link = $( this ),
				$module = $link.closest( moduleSelector ),
				linkId = $link.data( 'link-id' ),
				moduleName = $module.data( 'module-name' ),
				mode = $module.data( 'mode' );
			logger.log( moduleName, mode, 'link-click', { linkId: linkId } );

			// This is needed so this handler doesn't fire twice for links
			// that are inside a module that is inside another module.
			e.stopPropagation();
		},
		logImpression = function () {
			var $module = $( this ),
				moduleName = $module.data( 'module-name' ),
				mode = $module.data( 'mode' );
			logger.log( moduleName, mode, 'impression' );
		},
		uri = new mw.Uri();

	$modules
		.on( 'mouseenter', handleHover( 'in' ) )
		.on( 'mouseleave', handleHover( 'out' ) )
		.on( 'click', '[data-link-id]', handleClick );

	// Log the initial impressions only if the initial URI doesn't specify a module in overlay
	if ( !uri.fragment || !uri.fragment.match( /^\/homepage\/.*$/ ) ) {
		$modules.each( logImpression );
	}

	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		$content.find( moduleSelector )
			.on( 'click', '[data-link-id]', handleClick )
			.each( logImpression );
	} );
}() );