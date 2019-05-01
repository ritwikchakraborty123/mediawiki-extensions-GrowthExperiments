<?php

namespace GrowthExperiments\HomepageModules;

use Html;
use IContextSource;
use OOUI\ButtonWidget;

class Userpage extends BaseTaskModule {

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'start-userpage', $context );
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		return $this->getContext()->getUser()->getUserPage()->exists();
	}

	/**
	 * @inheritDoc
	 */
	protected function getUncompletedIcon() {
		return 'edit';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		$msgKey = $this->isCompleted() ?
			'growthexperiments-homepage-userpage-header-done' :
			'growthexperiments-homepage-userpage-header';
		return $this->getContext()->msg( $msgKey )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		$msg = $this->isCompleted() ?
			'growthexperiments-homepage-userpage-body-done' :
			'growthexperiments-homepage-userpage-body';
		$messageText = $this->getContext()->msg( $msg )
			->params( $this->getContext()->getUser()->getName() )
			->escaped();

		return $messageText . $this->getGuidelinesLink();
	}

	/**
	 * @inheritDoc
	 */
	protected function getFooter() {
		if ( $this->isCompleted() ) {
			$buttonMsg = 'growthexperiments-homepage-userpage-button-done';
			$buttonFlags = [];
			$linkId = 'userpage-edit';
		} else {
			$buttonMsg = 'growthexperiments-homepage-userpage-button';
			$buttonFlags = [ 'progressive' ];
			$linkId = 'userpage-create';
		}
		$button = new ButtonWidget( [
			'label' => $this->getContext()->msg( $buttonMsg )->text(),
			'flags' => $buttonFlags,
			'href' => $this->getContext()->getUser()->getUserPage()->getEditURL(),
		] );
		$button->setAttributes( [ 'data-link-id' => $linkId ] );

		return $button;
	}

	/**
	 * @return string HTML
	 */
	private function getGuidelinesLink() {
		$wikiId = wfWikiID();
		return Html::rawElement(
			'div',
			[ 'class' => 'growthexperiments-homepage-userpage-guidelines' ],
			Html::element(
				'a',
				[
					'href' => "https://www.wikidata.org/wiki/Special:GoToLinkedPage/$wikiId/Q4592334",
					'data-link-id' => 'userpage-guidelines'
				],
				$this->getContext()->msg( 'growthexperiments-homepage-userpage-guidelines' )->text()
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return 'oojs-ui.styles.icons-editing-core';
	}
}
