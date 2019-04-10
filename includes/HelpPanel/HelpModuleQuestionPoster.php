<?php

namespace GrowthExperiments\HelpPanel;

use ConfigException;
use GrowthExperiments\HelpPanel;
use GrowthExperiments\HomepageModules\Help;

class HelpModuleQuestionPoster extends QuestionPoster {

	/**
	 * @inheritDoc
	 */
	protected function getTag() {
		return Help::HELP_MODULE_QUESTION_TAG;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSectionHeader() {
		return $this->getContext()
			->msg( 'growthexperiments-help-panel-question-subject-template-from-homepage' )
			->inContentLanguage()->text();
	}

	/**
	 * @inheritDoc
	 * @throws ConfigException
	 */
	protected function getTargetTitle() {
		return HelpPanel::getHelpDeskTitle( $this->getContext()->getConfig() );
	}
}