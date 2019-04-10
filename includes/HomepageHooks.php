<?php

namespace GrowthExperiments;

use ConfigException;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\Tutorial;
use GrowthExperiments\Specials\SpecialHomepage;
use GrowthExperiments\Specials\SpecialImpact;
use JobQueueGroup;
use MediaWiki\MediaWikiServices;
use OutputPage;
use RequestContext;
use Skin;
use SkinTemplate;
use SpecialPage;
use Title;
use User;

class HomepageHooks {

	const HOMEPAGE_PREF_ENABLE = 'growthexperiments-homepage-enable';
	const HOMEPAGE_PREF_PT_LINK = 'growthexperiments-homepage-pt-link';

	/**
	 * Register Homepage and Impact special pages.
	 *
	 * @param array &$list
	 * @throws ConfigException
	 */
	public static function onSpecialPageInitList( &$list ) {
		if ( self::isHomepageEnabled() ) {
			$list[ 'Homepage' ] = SpecialHomepage::class;
			$list[ 'Impact' ] = SpecialImpact::class;
		}
	}

	/**
	 * @param User|null $user
	 * @return bool
	 * @throws ConfigException
	 */
	public static function isHomepageEnabled( User $user = null ) {
		return (
			MediaWikiServices::getInstance()->getMainConfig()->get( 'GEHomepageEnabled' ) &&
			( $user === null || $user->getBoolOption( self::HOMEPAGE_PREF_ENABLE ) )
		);
	}

	/**
	 * Make sure user pages have "User", "talk" and "homepage" tabs.
	 *
	 * @param SkinTemplate &$skin
	 * @param array &$links
	 * @throws \MWException
	 * @throws ConfigException
	 */
	public static function onSkinTemplateNavigationUniversal( SkinTemplate &$skin, array &$links ) {
		$user = $skin->getUser();
		if ( !self::isHomepageEnabled( $user ) || self::isMobile( $skin ) ) {
			return;
		}

		$title = $skin->getTitle();
		$userpage = $user->getUserPage();
		$usertalk = $user->getTalkPage();

		if ( $title->isSpecial( 'Homepage' ) ) {
			unset( $links[ 'namespaces' ][ 'special' ] );
			$links[ 'namespaces' ][ 'homepage' ] = $skin->tabAction(
				$title, 'growthexperiments-homepage-tab', true
			);
			$links[ 'namespaces' ][ 'user' ] = $skin->tabAction(
				$userpage, 'nstab-user', false, '', true
			);
			$links[ 'namespaces' ][ 'talk' ] = $skin->tabAction(
				$usertalk, 'talk', false, '', true
			);
			return;
		}

		if ( $title->equals( $userpage ) ||
			$title->isSubpageOf( $userpage ) ||
			$title->equals( $usertalk ) ||
			$title->isSubpageOf( $usertalk )
		) {
			$source = 'userpagetab';
			if ( $title->equals( $usertalk ) || $title->isSubpageOf( $usertalk ) ) {
				$source = 'usertalkpagetab';
			}
			$links[ 'namespaces' ] = array_merge(
				[ 'homepage' => $skin->tabAction(
					SpecialPage::getTitleFor( 'Homepage' ),
					'growthexperiments-homepage-tab',
					false,
					'source=' . $source . '&namespace=' . $title->getNamespace()
				) ],
				$links[ 'namespaces' ]
			);
		}
	}

	/**
	 * Conditionally make the userpage link go to the homepage.
	 *
	 * @param array &$personal_urls
	 * @param Title &$title
	 * @param SkinTemplate $sk
	 * @throws \MWException
	 * @throws ConfigException
	 */
	public static function onPersonalUrls( &$personal_urls, &$title, $sk ) {
		$user = $sk->getUser();
		if ( !self::isHomepageEnabled( $user ) || self::isMobile( $sk ) ) {
			return;
		}

		if ( $user->getBoolOption( self::HOMEPAGE_PREF_PT_LINK ) ) {
			$homepage = SpecialPage::getTitleFor( 'Homepage' );
			$personal_urls[ 'userpage' ][ 'href' ] = $homepage->getLinkURL(
				'source=personaltoolslink&namespace=' . $title->getNamespace()
			);
		}
	}

	/**
	 * @param SkinTemplate $skin
	 * @return bool Whether the given skin is considered "mobile"
	 */
	private static function isMobile( SkinTemplate $skin ) {
		return $skin->getSkinName() === 'minerva';
	}

	/**
	 * Register preferences to control the homepage.
	 *
	 * @param User $user
	 * @param array &$preferences Preferences object
	 * @throws ConfigException
	 */
	public static function onGetPreferences( $user, &$preferences ) {
		if ( !self::isHomepageEnabled() ) {
			return;
		}

		$preferences[ self::HOMEPAGE_PREF_ENABLE ] = [
			'type' => 'toggle',
			'section' => 'personal/homepage',
			'label-message' => self::HOMEPAGE_PREF_ENABLE,
		];

		$preferences[ self::HOMEPAGE_PREF_PT_LINK ] = [
			'type' => 'toggle',
			'section' => 'personal/homepage',
			'label-message' => self::HOMEPAGE_PREF_PT_LINK,
			'hide-if' => [ '!==', self::HOMEPAGE_PREF_ENABLE, '1' ],
		];

		$preferences[ Mentor::MENTOR_PREF ] = [
			'type' => 'api',
		];

		$preferences[ Tutorial::TUTORIAL_PREF ] = [
			'type' => 'api',
		];
	}

	/**
	 * Enable the homepage for a percentage of new local accounts.
	 *
	 * @param User $user
	 * @param bool $autocreated
	 * @throws ConfigException
	 */
	public static function onLocalUserCreated( User $user, $autocreated ) {
		if ( !self::isHomepageEnabled() ) {
			return;
		}

		// Enable the homepage for a percentage of non-autocreated users.
		$config = RequestContext::getMain()->getConfig();
		$enablePercentage = $config->get( 'GEHomepageNewAccountEnablePercentage' );
		if ( $user->isLoggedIn() && !$autocreated && rand( 0, 99 ) < $enablePercentage ) {
			$user->setOption( self::HOMEPAGE_PREF_ENABLE, 1 );
			$user->setOption( self::HOMEPAGE_PREF_PT_LINK, 1 );
			$user->saveSettings();
		}
	}

	/**
	 * ListDefinedTags and ChangeTagsListActive hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ListDefinedTags
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangeTagsListActive
	 *
	 * @param array &$tags The list of tags.
	 * @throws ConfigException
	 */
	public static function onListDefinedTags( &$tags ) {
		if ( self::isHomepageEnabled() ) {
			$tags[] = Help::HELP_MODULE_QUESTION_TAG;
			$tags[] = Mentorship::MENTORSHIP_MODULE_QUESTION_TAG;
		}
	}

	/**
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 * @throws ConfigException
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		// Update user preference to show they've visited the configured tutorial page.
		if ( !$out->getUser()->isLoggedIn() || !self::isHomepageEnabled( $out->getUser() ) ) {
			return;
		}
		// User has already visited the tutorial, return.
		if ( $out->getUser()->getBoolOption( Tutorial::TUTORIAL_PREF ) ) {
			return;
		}
		$tutorialTitle = $out->getConfig()->get( Tutorial::TUTORIAL_TITLE_CONFIG );
		if ( $tutorialTitle ) {
			$tutorialTitle = Title::newFromText( $tutorialTitle );
		}
		if ( $tutorialTitle && $tutorialTitle->exists() &&
			$tutorialTitle->equals( $out->getTitle() ) ) {
			$job = new TutorialVisitJob( [ 'userId' => $out->getUser()->getId() ] );
			JobQueueGroup::singleton()->lazyPush( $job );
		}
	}

}
