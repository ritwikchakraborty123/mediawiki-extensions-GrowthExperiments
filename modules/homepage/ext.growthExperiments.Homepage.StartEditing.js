( function () {
	var StartEditingDialog = require( './ext.growthExperiments.Homepage.StartEditingDialog.js' ),
		Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		defaultTaskTypes = require( '../homepage/suggestededits/DefaultTaskTypes.json' ),
		logger = new Logger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		GrowthTasksApi = require( './suggestededits/ext.growthExperiments.Homepage.GrowthTasksApi.js' ),
		api = new GrowthTasksApi( {
			defaultTaskTypes: defaultTaskTypes,
			isMobile: OO.ui.isMobile(),
			context: 'startEditingDialog'
		} );

	/**
	 * Launch the suggested edits initiation dialog.
	 *
	 * @param {string} type 'startediting' or 'suggestededits'
	 * @param {string} mode Rendering mode. See constants in HomepageModule.php
	 * @return {jQuery.Promise<boolean>} Resolves when the dialog is closed, indicates whether
	 *   initiation was successful or cancelled.
	 */
	function launchCta( type, mode ) {
		var lifecycle, dialog, windowManager;

		dialog = new StartEditingDialog( {
			mode: mode,
			useTopicSelector: type === 'startediting',
			activateWhenDone: type === 'startediting'
		}, logger, api );
		windowManager = new OO.ui.WindowManager( {
			modal: true
		} );

		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		lifecycle = windowManager.openWindow( dialog );
		return lifecycle.closing.then( function ( data ) {
			return ( data && data.action === 'activate' );
		} );
	}

	function setupCta( $container ) {
		var ctaButton,
			$startEditingCta = $container.find( '#mw-ge-homepage-startediting-cta' ),
			$suggestedEditsInfo = $container.find( '#mw-ge-homepage-suggestededits-info' ),
			$buttonElement = $startEditingCta.length ? $startEditingCta : $suggestedEditsInfo,
			buttonType = $startEditingCta.length ? 'startediting' : 'suggestededits',
			mode = $buttonElement.closest( '.growthexperiments-homepage-module' ).data( 'mode' );
		if ( $buttonElement.length === 0 ) {
			return;
		}

		ctaButton = OO.ui.ButtonWidget.static.infuse( $buttonElement );

		ctaButton.on( 'click', function () {
			if (
				buttonType === 'startediting' &&
				mw.user.options.get( 'growthexperiments-homepage-suggestededits-activated' )
			) {
				// already set up, just open suggested edits
				if ( mode === 'mobile-overlay' ) {
					// we don't want users to return to the start overlay when they close
					// suggested edits
					window.history.replaceState( null, null, '#/homepage/suggested-edits' );
					window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
				} else if ( mode === 'mobile-details' ) {
					window.location.href = mw.util.getUrl(
						new mw.Title( 'Special:Homepage/suggested-edits' ).toString()
					);
				}
				return;
			}

			if ( buttonType === 'startediting' ) {
				logger.log( 'start-startediting', mode, 'se-cta-click' );
			} else {
				logger.log( 'suggested-edits', mode, 'se-info-click' );
			}
			launchCta( buttonType, mode ).done( function ( activated ) {
				if ( activated ) {
					// No-op; logging and everything else is done within the dialog,
					// as it is kept open during setup of the suggested edits module
					// to make the UI change less disruptive.
				} else if ( buttonType === 'startediting' ) {
					logger.log( 'start-startediting', mode, 'se-cancel-activation' );
				}
			} );
		} );
	}

	// Try setup for desktop mode and server-side-rendered mobile mode
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js
	// eslint-disable-next-line no-jquery/no-global-selector
	setupCta( $( '.growthexperiments-homepage-container' ) );

	// Allow activation from a guided tour
	mw.trackSubscribe( 'growthexperiments.startediting', function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		var mode = $( '.growthexperiments-homepage-module' ).data( 'mode' );
		launchCta( 'suggestededits', mode );
	} );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'start' || moduleName === 'suggested-edits' ) {
			setupCta( $content );
		}
	} );

}() );
