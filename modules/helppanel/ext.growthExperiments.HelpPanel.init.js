( function () {
	var veState = mw.loader.getState( 'ext.visualEditor.desktopArticleTarget.init' );
	if ( !mw.config.get( 'wgGEHelpPanelEnabled' ) ) {
		return;
	}

	// If VisualEditor is available, add the HelpPanel module as a plugin
	// This loads it alongside VE's modules when VE is activated, but doesn't load VE's init module
	// if it wasn't already going to be loaded
	if ( veState === 'loading' || veState === 'loaded' || veState === 'ready' ) {
		mw.loader.using( 'ext.visualEditor.desktopArticleTarget.init' ).done( function () {
			mw.libs.ve.addPlugin( 'ext.growthExperiments.HelpPanel' );
		} );
	}

	// MobileFrontend's editor doesn't have a similar plugin system, so instead load the HelpPanel
	// module separately when the editor begins loading
	mw.hook( 'mobileFrontend.editorOpening' ).add( function () {
		mw.loader.load( 'ext.growthExperiments.HelpPanel' );
	} );
}() );
