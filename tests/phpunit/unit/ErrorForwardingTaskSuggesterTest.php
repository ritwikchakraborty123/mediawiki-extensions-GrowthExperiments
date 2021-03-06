<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester
 */
class ErrorForwardingTaskSuggesterTest extends MediaWikiUnitTestCase {

	public function testSuggest() {
		$user = new UserIdentityValue( 1, 'Foo', 1 );
		$suggester = new ErrorForwardingTaskSuggester( StatusValue::newFatal( 'foo' ) );
		$result = $suggester->suggest( $user );
		$this->assertInstanceOf( StatusValue::class, $result );
		$this->assertTrue( $result->hasMessage( 'foo' ) );
	}

}
