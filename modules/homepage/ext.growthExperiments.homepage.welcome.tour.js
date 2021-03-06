( function ( gt ) {
	var welcomeTour, step,
		HomepageModuleLogger = require( 'ext.growthExperiments.Homepage.Logger' ),
		homepageModuleLogger = new HomepageModuleLogger(
			mw.config.get( 'wgGEHomepageLoggingEnabled' ),
			mw.config.get( 'wgGEHomepagePageviewToken' )
		),
		homepageVariant = mw.user.options.get( 'growthexperiments-homepage-variant' );

	/**
	 * @param {Object} guider The guider configuration object
	 * @param {boolean} isAlternativeClose Legacy parameter, should be ignored.
	 * @param {string} closeMethod Guider close method: 'xButton', 'escapeKey', 'clickOutside'
	 */
	function logTourClose( guider, isAlternativeClose, closeMethod ) {
		var type = {
			xButton: 'close-icon',
			escapeKey: 'should-not-happen',
			clickOutside: 'outside-click'
		}[ closeMethod ];

		homepageModuleLogger.log( 'generic', 'desktop', 'welcome-close', { type: type } );
	}

	/**
	 * Annoyingly, the tour builder declares the 'end' button in such a way that it breaks
	 * the onClick and onClose callbacks. Set up logging via a manual onclick handler instead.
	 *
	 * This method can be passed as an onShow handler.
	 *
	 * @param {Object} guider The guider configuration object
	 */
	function setupCloseButtonLogging( guider ) {
		guider.elem.find( '.guidedtour-end-button, .guidedtour-next-button' ).click( function () {
			homepageModuleLogger.log( 'generic', 'desktop', 'welcome-close', { type: 'button' } );
		} );
	}

	welcomeTour = new gt.TourBuilder( {
		name: 'homepage_welcome',
		isSinglePage: true,
		shouldLog: true
	} );
	if ( homepageVariant === 'A' ) {
		welcomeTour.firstStep( {
			name: 'welcome',
			title: mw.message( 'growthexperiments-tour-welcome-title' )
				.params( [ mw.user ] )
				.parse(),
			description: mw.message( 'growthexperiments-tour-welcome-description' )
				.params( [ mw.user ] )
				.parse(),
			attachTo: '#pt-userpage',
			position: 'bottom',
			overlay: false,
			autoFocus: true,
			buttons: [ {
				action: 'end',
				namemsg: 'growthexperiments-tour-response-button-okay'
			} ],
			onShow: setupCloseButtonLogging,
			onClose: logTourClose
		} );
	} else if ( homepageVariant === 'C' ) {
		step = welcomeTour.firstStep( {
			name: 'welcome',
			title: mw.message( 'growthexperiments-tour-welcome-title' )
				.params( [ mw.user ] )
				.parse(),
			description: mw.message( 'growthexperiments-tour-welcome-description-c' ).parse(),
			attachTo: '#pt-userpage',
			position: 'bottom',
			overlay: false,
			autoFocus: true,
			buttons: [ {
				// There is way to influence the button icon without terrible hacks,
				// so use the 'next' button which has the right icon but breaks the onclick
				// callback, and define a fake next step and use its onShow callback instead.
				action: 'next'
			} ],
			onShow: setupCloseButtonLogging,
			onClose: logTourClose
		} );
		welcomeTour.step( {
			name: 'fake',
			description: 'also fake',
			onShow: function () {
				mw.guidedTour.endTour();
				mw.track( 'growthexperiments.startediting' );
				// cancel displaying the guider
				return true;
			}
		} );
		step.next( 'fake' );
	} else if ( homepageVariant === 'D' ) {
		welcomeTour.firstStep( {
			name: 'welcome',
			title: mw.message( 'growthexperiments-tour-welcome-title' )
				.params( [ mw.user ] )
				.parse(),
			description: mw.message( 'growthexperiments-tour-welcome-description-d' ).parse(),
			attachTo: '#pt-userpage',
			position: 'bottom',
			overlay: false,
			autoFocus: true,
			buttons: [ {
				action: 'end',
				namemsg: 'growthexperiments-tour-response-button-okay'
			} ],
			onShow: setupCloseButtonLogging,
			onClose: logTourClose
		} );
	}
	mw.guidedTour.launchTour( 'homepage_welcome' );
	homepageModuleLogger.log( 'generic', 'desktop', 'welcome-impression' );
	new mw.Api().saveOption(
		'growthexperiments-tour-homepage-welcome',
		'1'
	);
}( mw.guidedTour ) );
