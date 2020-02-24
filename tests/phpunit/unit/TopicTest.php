<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWikiUnitTestCase;
use MessageLocalizer;

/**
 * @covers \GrowthExperiments\NewcomerTasks\Topic\Topic
 */
class TopicTest extends MediaWikiUnitTestCase {

	public function testTopic() {
		$fakeContext = $this->getMockBuilder( MessageLocalizer::class )
			->setMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$fakeContext->method( 'msg' )->willReturnCallback( function ( $id ) {
			return $this->getMockMessage( $id );
		} );
		/** @var MessageLocalizer $fakeContext */

		$topic = new Topic( 'foo' );
		$this->assertSame( 'foo', $topic->getId() );
		$this->assertNull( $topic->getGroupId() );
		$this->assertSame( 'growthexperiments-homepage-suggestededits-topic-name-foo',
			$topic->getName( $fakeContext )->text() );
		$this->assertSame( [
			'id' => 'foo',
			'name' => 'growthexperiments-homepage-suggestededits-topic-name-foo',
			'groupId' => null,
			'groupName' => null,
		], $topic->toArray( $fakeContext ) );

		$topic = new Topic( 'foo', 'bar' );
		$this->assertSame( 'bar', $topic->getGroupId() );
		$this->assertSame( 'growthexperiments-homepage-suggestededits-topic-group-name-bar',
			$topic->getGroupName( $fakeContext )->text() );
		$this->assertSame( [
			'id' => 'foo',
			'name' => 'growthexperiments-homepage-suggestededits-topic-name-foo',
			'groupId' => 'bar',
			'groupName' => 'growthexperiments-homepage-suggestededits-topic-group-name-bar',
		], $topic->toArray( $fakeContext ) );
	}

}
