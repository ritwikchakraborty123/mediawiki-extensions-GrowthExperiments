<?php

namespace GrowthExperiments;

use GrowthExperiments\Mentorship\ChangeMentor;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorManager;
use IContextSource;
use LogPager;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Status;
use User;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\ChangeMentor
 */
class ChangeMentorTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( ChangeMentor::class,
			new ChangeMentor(
				$this->getUserMock( 'Mentee', 1 ),
				$this->getUserMock( 'Performer', 2 ),
				$this->getContextMock(),
				new NullLogger(),
				new Mentor( $this->getUserMock( 'OldMentor', 3 ), 'o/' ),
				$this->getLogPagerMock(),
				$this->getMentorManagerMock()
			)
		);
	}

	/**
	 * @covers ::wasMentorChanged
	 */
	public function testWasMentorChangedSuccess() {
		$logPagerMock = $this->getLogPagerMock();
		$resultMock = $this->getMockBuilder( IResultWrapper::class )
			->getMock();
		$resultMock->method( 'fetchRow' )->willReturn( [ 'foo' ] );
		$logPagerMock->method( 'getResult' )->willReturn( $resultMock );
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			$this->getContextMock(),
			new NullLogger(),
			new Mentor( $this->getUserMock( 'OldMentor', 3 ), 'o/' ),
			$logPagerMock,
			$this->getMentorManagerMock()
		);
		$this->assertNotFalse( $changeMentor->wasMentorChanged() );
	}

	/**
	 * @covers ::validate
	 */
	public function testValidateMenteeIdZero(): void {
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 0 ),
			$this->getUserMock( 'Performer', 2 ),
			$this->getContextMock(),
			new NullLogger(),
			new Mentor( $this->getUserMock( 'OldMentor', 3 ), 'o/' ),
			$this->getLogPagerMock(),
			$this->getMentorManagerMock()
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $this->getUserMock( 'NewMentor', 4 );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertFalse( $status->isGood() );
		$this->assertSame(
			'growthexperiments-homepage-claimmentee-no-user',
			$status->getErrors()[0]['message']
		);
	}

	/**
	 * @covers ::validate
	 */
	public function testValidateSuccess(): void {
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			$this->getContextMock(),
			new NullLogger(),
			new Mentor( $this->getUserMock( 'OldMentor', 3 ), 'o/' ),
			$this->getLogPagerMock(),
			$this->getMentorManagerMock()
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $this->getUserMock( 'NewMentor', 4 );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertTrue( $status->isGood() );
	}

	/**
	 * @covers ::validate
	 */
	public function testValidateOldMentorNewMentorEquality(): void {
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			$this->getContextMock(),
			new NullLogger(),
			new Mentor( $this->getUserMock( 'SameMentor', 3 ), 'o/' ),
			$this->getLogPagerMock(),
			$this->getMentorManagerMock()
		);
		$changeMentorWrapper = TestingAccessWrapper::newFromObject( $changeMentor );
		$changeMentorWrapper->newMentor = $this->getUserMock( 'SameMentor', 3 );
		/** @var Status $status */
		$status = $changeMentorWrapper->validate();
		$this->assertFalse( $status->isGood() );
	}

	/**
	 * @covers ::execute
	 * @covers ::validate
	 */
	public function testExecuteBadStatus(): void {
		$changeMentor = new ChangeMentor(
			$this->getUserMock( 'Mentee', 1 ),
			$this->getUserMock( 'Performer', 2 ),
			$this->getContextMock(),
			new NullLogger(),
			new Mentor( $this->getUserMock( 'SameMentor', 3 ), 'o/' ),
			$this->getLogPagerMock(),
			$this->getMentorManagerMock()
		);
		$status = $changeMentor->execute( $this->getUserMock( 'SameMentor', 3 ), 'test' );
		$this->assertFalse( $status->isOK() );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-claimmentee-already-mentor' ) );
	}

	/**
	 * @param string $name
	 * @param int|null $id
	 * @return MockObject|User
	 */
	private function getUserMock( string $name, int $id ) {
		$user = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getName', 'getId' ] )
			->getMock();
		$user->method( 'getName' )->willReturn( $name );
		$user->method( 'getId' )->willReturn( $id );
		return $user;
	}

	/**
	 * @return MockObject|IContextSource
	 */
	private function getContextMock() {
		return $this->getMockBuilder( IContextSource::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return MockObject|LogPager
	 */
	private function getLogPagerMock() {
		return $this->getMockBuilder( LogPager::class )
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * @return MockObject|MentorManager
	 */
	private function getMentorManagerMock() {
		return $this->getMockBuilder( MentorManager::class )
			->getMockForAbstractClass();
	}

}
