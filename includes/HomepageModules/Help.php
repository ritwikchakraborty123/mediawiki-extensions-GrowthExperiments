<?php

namespace GrowthExperiments\HomepageModules;

use ConfigException;
use GrowthExperiments\HelpPanel;
use OOUI\ButtonWidget;
use OOUI\Tag;

class Help extends BaseSidebarModule {

	public function __construct() {
		parent::__construct( 'help' );
	}

	/**
	 * @return string
	 */
	protected function getHeader() {
		return $this->getContext()->msg( 'growthexperiments-homepage-help-header' )->text();
	}

	/**
	 * @return string|string[]
	 */
	protected function getModules() {
		return 'ext.growthExperiments.Homepage.Help';
	}

	/**
	 * @return string
	 * @throws ConfigException
	 */
	protected function getBody() {
		$helpPanelLinkData = HelpPanel::getHelpPanelLinks(
			$this->getContext(),
			$this->getContext()->getConfig()
		);
		return $helpPanelLinkData['helpPanelLinks'] . $helpPanelLinkData['viewMoreLink'];
	}

	/**
	 * @return Tag|string
	 * @throws ConfigException
	 */
	protected function getFooter() {
		return ( new Tag( 'div' ) )
			->addClasses( [ 'mw-ge-homepage-help-cta' ] )
			->appendContent( new ButtonWidget( [
				'id' => 'mw-ge-homepage-help-cta',
				'href' => HelpPanel::getHelpDeskTitle(
					$this->getContext()->getConfig()
				)->getLinkURL(),
				'label' => $this->getContext()->msg(
					'growthexperiments-homepage-help-ask-help-desk'
				)->text(),
				'infusable' => true,
			] ) );
	}

}