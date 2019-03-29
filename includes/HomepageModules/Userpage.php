<?php

namespace GrowthExperiments\HomepageModules;

use Html;
use IContextSource;
use OOUI\ButtonWidget;
use OOUI\IconWidget;

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
	protected function getHeader() {
		if ( $this->isCompleted() ) {
			$msg = 'growthexperiments-homepage-userpage-header-done';
			$icon = 'check';
		} else {
			$msg = 'growthexperiments-homepage-userpage-header';
			$icon = 'edit';
		}
		return new IconWidget( [ 'icon' => $icon ] ) .
			$this->getContext()->msg( $msg )
				->params( $this->getContext()->getUser()->getName() )
				->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheader() {
		$msg = $this->isCompleted() ?
			'growthexperiments-homepage-userpage-body-done' :
			'growthexperiments-homepage-userpage-body';
		return $this->getContext()->msg( $msg )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		if ( $this->isCompleted() ) {
			$buttonMsg = 'growthexperiments-homepage-userpage-button-done';
			$buttonFlags = [];
			$linkId = 'userpage-edit';
		} else {
			$buttonMsg = 'growthexperiments-homepage-userpage-button';
			$buttonFlags = [ 'progressive', 'primary' ];
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
	 * @inheritDoc
	 */
	protected function getFooter() {
		$wikiId = wfWikiID();
		return Html::element(
			'a',
			[
				'href' => "https://www.wikidata.org/wiki/Special:GoToLinkedPage/$wikiId/Q4592334",
				'data-link-id' => 'userpage-guidelines'
			],
			$this->getContext()->msg( 'growthexperiments-homepage-userpage-guidelines' )->text()
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return 'oojs-ui.styles.icons-editing-core';
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		return $this->getContext()->getUser()->getUserPage()->exists();
	}
}
