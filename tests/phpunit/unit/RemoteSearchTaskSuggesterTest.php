<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\Matcher\InvokedRecorder;
use PHPUnit\Framework\MockObject\MockObject;
use RawMessage;
use Status;
use StatusValue;
use Title;
use TitleFactory;
use TitleValue;

/**
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggester
 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\SearchTaskSuggester
 * @covers \GrowthExperiments\Util::getApiUrl
 * @covers \GrowthExperiments\Util::getIteratorFromTraversable
 */
class RemoteSearchTaskSuggesterTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideSuggest
	 * @param string[] $taskTypeSpec All configured task types on the server. See getTaskTypes().
	 * @param string[] $topicSpec All configured topics on the server. See getTopics().
	 * @param array $requests [ [ 'params' => [...], 'response' => ... ], ... ] where params is
	 *   a list of asserted query parameters (null means asserted to be not present), response is
	 *   JSON data (in PHP form) or a StatusValue with errors
	 * @param string[] $taskFilter
	 * @param string[] $topicFilter
	 * @param int|null $limit
	 * @param TaskSet|StatusValue $expectedTaskSet
	 */
	public function testSuggest(
		$taskTypeSpec, $topicSpec, $requests, $taskFilter, $topicFilter, $limit, $expectedTaskSet
	) {
		// FIXME null task/topic filter values are not tested, but they are not implemented anyway

		$templateProvider = $this->getMockTemplateProvider( $expectedTaskSet instanceof TaskSet );
		$requestFactory = $this->getMockRequestFactory( $requests );
		$titleFactory = $this->getMockTitleFactory();

		$user = new UserIdentityValue( 1, 'Foo', 1 );
		$taskTypes = $this->getTaskTypes( $taskTypeSpec );
		$topics = $this->getTopics( $topicSpec );
		$suggester = new RemoteSearchTaskSuggester( $templateProvider, $requestFactory, $titleFactory,
			'https://example.com', $taskTypes, $topics, [] );

		$taskSet = $suggester->suggest( $user, $taskFilter, $topicFilter, $limit );
		if ( $expectedTaskSet instanceof StatusValue ) {
			$this->assertInstanceOf( StatusValue::class, $taskSet );
			$this->assertEquals( $expectedTaskSet->getErrors(), $taskSet->getErrors() );
		} else {
			$this->assertInstanceOf( TaskSet::class, $taskSet );
			$this->assertSame( $expectedTaskSet->getOffset(), $taskSet->getOffset() );
			$this->assertSame( $expectedTaskSet->getTotalCount(), $taskSet->getTotalCount() );
			$this->assertSame( count( $expectedTaskSet ), count( $taskSet ) );
			// Responses are shuffled due to T242057 so we need order-insensitive comparison.
			$expectedTaskData = $this->taskSetToArray( $expectedTaskSet );
			$actualTaskData = $this->taskSetToArray( $taskSet );
			$this->assertArrayEquals( $expectedTaskData, $actualTaskData, false, false );
		}
	}

	public function provideSuggest() {
		$copyedit = new TaskType( 'copyedit', TaskType::DIFFICULTY_EASY );
		$link = new TaskType( 'link', TaskType::DIFFICULTY_EASY );
		return [
			'success' => [
				// all configured task types on the server (see getTaskTypes() for format)
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ] ],
				// all configured topics on the server (see getTopics() for format)
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				// expectations + response for each request the suggester should make
				'requests' => [
					[
						// a list of asserted query parameters (null means asserted to be not present)
						'params' => [
							'action' => 'query',
							'list' => 'search',
							'srsearch' => 'hastemplate:"Copy-1|Copy-2"',
							'srnamespace' => '0',
						],
						// JSON data (in PHP form) or a StatusValue with errors
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
									[ 'ns' => 0, 'title' => 'Bar' ],
								],
								'searchinfo' => [
									'totalhits' => 100,
								],
							],
						],
					],
				],
				// parameters passed to the suggest() call
				'taskFilter' => null,
				'topicFilter' => null,
				'limit' => null,
				// expected return value from suggest()
				'expectedTaskSet' => new TaskSet( [
					new Task( $copyedit, new TitleValue( 0, 'Foo' ) ),
					new Task( $copyedit, new TitleValue( 0, 'Bar' ) ),
				], 100, 0 ),
			],
			'multiple queries' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ], 'link' => [ 'Link-1' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [
							'srsearch' => 'hastemplate:"Copy-1|Copy-2"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
									[ 'ns' => 0, 'title' => 'Bar' ],
									[ 'ns' => 0, 'title' => 'Baz' ],
									[ 'ns' => 0, 'title' => 'Boom' ],
								],
								'searchinfo' => [
									'totalhits' => 100,
								],
							],
						],
					],
					[
						'params' => [
							'srsearch' => 'hastemplate:"Link-1"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Bang' ],
								],
								'searchinfo' => [
									'totalhits' => 50,
								],
							],
						],
					],
				],
				'taskFilter' => null,
				'topicFilter' => null,
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					new Task( $copyedit, new TitleValue( 0, 'Foo' ) ),
					new Task( $link, new TitleValue( 0, 'Bang' ) ),
					new Task( $copyedit, new TitleValue( 0, 'Bar' ) ),
					new Task( $copyedit, new TitleValue( 0, 'Baz' ) ),
					new Task( $copyedit, new TitleValue( 0, 'Boom' ) ),
				], 150, 0 ),
			],
			'limit' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ], 'link' => [ 'Link-1' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [
							'srlimit' => '2',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
									[ 'ns' => 0, 'title' => 'Bar' ],
								],
								'searchinfo' => [
									'totalhits' => 100,
								],
							],
						],
					],
					[
						'params' => [
							'srlimit' => '2',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Baz' ],
									[ 'ns' => 0, 'title' => 'Boom' ],
								],
								'searchinfo' => [
									'totalhits' => 50,
								],
							],
						],
					],
				],
				'taskFilter' => null,
				'topicFilter' => null,
				'limit' => 2,
				'expectedTaskSet' => new TaskSet( [
					new Task( $copyedit, new TitleValue( 0, 'Foo' ) ),
					new Task( $link, new TitleValue( 0, 'Baz' ) ),
				], 150, 0 ),
			],
			'task type filter' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ], 'link' => [ 'Link-1' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [
							'srsearch' => 'hastemplate:"Copy-1|Copy-2"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
								],
								'searchinfo' => [
									'totalhits' => 100,
								],
							],
						],
					],
				],
				'taskFilter' => [ 'copyedit' ],
				'topicFilter' => null,
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					new Task( $copyedit, new TitleValue( 0, 'Foo' ) ),
				], 100, 0 ),
			],
			'topic filter' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ], 'link' => [ 'Link-1' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [
							'srsearch' => 'hastemplate:"Copy-1|Copy-2" morelikethis:"Music|Painting|Physics|Biology"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Foo' ],
								],
								'searchinfo' => [
									'totalhits' => 100,
								],
							],
						],
					],
					[
						'params' => [
							'srsearch' => 'hastemplate:"Link-1" morelikethis:"Music|Painting|Physics|Biology"',
						],
						'response' => [
							'query' => [
								'search' => [
									[ 'ns' => 0, 'title' => 'Baz' ],
									[ 'ns' => 0, 'title' => 'Boom' ],
								],
								'searchinfo' => [
									'totalhits' => 50,
								],
							],
						],
					],
				],
				'taskFilter' => null,
				'topicFilter' => [ 'art', 'science' ],
				'limit' => null,
				'expectedTaskSet' => new TaskSet( [
					new Task( $copyedit, new TitleValue( 0, 'Foo' ) ),
					new Task( $link, new TitleValue( 0, 'Baz' ) ),
					new Task( $link, new TitleValue( 0, 'Boom' ) ),
				], 150, 0 ),
			],
			'http error' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [],
						'response' => StatusValue::newFatal( 'foo' ),
					],
				],
				'taskFilter' => null,
				'topicFilter' => null,
				'limit' => null,
				'expectedTaskSet' => StatusValue::newFatal( 'foo' ),
			],
			'api error' => [
				'taskTypes' => [ 'copyedit' => [ 'Copy-1', 'Copy-2' ] ],
				'topics' => [ 'art' => [ 'Music', 'Painting' ], 'science' => [ 'Physics', 'Biology' ] ],
				'requests' => [
					[
						'params' => [],
						'response' => [
							'errors' => [
								[ 'text' => 'foo' ],
							],
						],
					],
				],
				'taskFilter' => null,
				'topicFilter' => null,
				'limit' => null,
				'expectedTaskSet' => StatusValue::newFatal( new RawMessage( 'foo' ) ),
			],
		];
	}

	/**
	 * @param bool $expectsToBeCalled
	 * @return TemplateProvider|MockObject
	 */
	private function getMockTemplateProvider( bool $expectsToBeCalled ) {
		$templateProvider = $this->getMockBuilder( TemplateProvider::class )
			->disableOriginalConstructor()
			->setMethods( [ 'fill' ] )
			->getMock();
		$templateProvider->expects( $expectsToBeCalled ? $this->once() : $this->never() )
			->method( 'fill' );
		return $templateProvider;
	}

	/**
	 * @param array $requests [ [ 'params' => [...], 'response' => ... ], ... ] where params is
	 *   a list of asserted query parameters (null means asserted to be not present), response is
	 *   JSON data (in PHP form) or a StatusValue with errors
	 * @return HttpRequestFactory|MockObject
	 */
	protected function getMockRequestFactory( array $requests ) {
		$requestFactory = $this->getMockBuilder( HttpRequestFactory::class )
			->disableOriginalConstructor()
			->setMethods( [ 'create', 'getUserAgent' ] )
			->getMock();
		$requestFactory->method( 'getUserAgent' )->willReturn( 'Foo' );

		$numRequests = count( $requests );
		$numErrors = count( array_filter( $requests, function ( $request ) {
			return $request['response'] instanceof StatusValue;
		} ) );
		$expectation = $numErrors ? $this->exactlyBetween( 1, $numRequests - $numErrors + 1 )
			: $this->exactly( $numRequests );
		$requestFactory->expects( $expectation )
			->method( 'create' )
			->willReturnCallback( function ( $url ) use ( &$requests ) {
				$actualParams = wfCgiToArray( parse_url( $url )['query'] );
				$request = array_shift( $requests );
				foreach ( $request['params'] as $key => $expectedValue ) {
					if ( $expectedValue === null ) {
						$this->assertArrayNotHasKey( $key, $actualParams,
							"found URL parameter that should not have been present: $key "
							. "(with value >>$actualParams[$key]<<)" );
					} else {
						$this->assertArrayHasKey( $key, $actualParams, "expected URL parameter missing: $key" );
						$this->assertSame( $expectedValue, $actualParams[$key],
							"wrong URL parameter value for parameter $key: "
							. "expected >>$expectedValue<<, found >>$actualParams[$key]<<" );
					}
				}

				if ( $request['response'] instanceof StatusValue ) {
					$status = Status::wrap( $request['response'] );
					$response = '';
				} else {
					$status = StatusValue::newGood();
					$response = json_encode( $request['response'] );
				}

				$request = $this->getMockBuilder( MWHttpRequest::class )
					->disableOriginalConstructor()
					->setMethods( [ 'execute', 'getContent' ] )
					->getMock();
				$request->method( 'execute' )->willReturn( $status );
				$request->method( 'getContent' )->willReturn( $response );
				return $request;
			} );
		return $requestFactory;
	}

	/**
	 * @return TitleFactory|MockObject
	 */
	protected function getMockTitleFactory() {
		$titleFactory = $this->getMockBuilder( TitleFactory::class )
			->disableOriginalConstructor()
			->setMethods( [ 'newFromText' ] )
			->getMock();
		$titleFactory->method( 'newFromText' )->willReturnCallback( function ( $dbKey, $ns ) {
			$title = $this->getMockBuilder( Title::class )
				->disableOriginalConstructor()
				->setMethods( [ 'getNamespace', 'getDBkey' ] )
				->getMock();
			$title->method( 'getNamespace' )->willReturn( $ns );
			$title->method( 'getDBkey' )->willReturn( $dbKey );
			return $title;
		} );
		return $titleFactory;
	}

	/**
	 * @param string[] $spec [ task type id => [ title, ... ], ... ]
	 * @return TemplateBasedTaskType[]
	 */
	private function getTaskTypes( array $spec ) {
		$taskTypes = [];
		foreach ( $spec as $topicId => $titleNames ) {
			$titleValues = [];
			foreach ( $titleNames as $titleName ) {
				$titleValues[] = new TitleValue( NS_TEMPLATE, $titleName );
			}
			$taskTypes[] = new TemplateBasedTaskType( $topicId, TaskType::DIFFICULTY_EASY, [],
				$titleValues );
		}
		return $taskTypes;
	}

	/**
	 * @param string[] $spec [ topic id => [ title, ... ], ... ]
	 * @return MorelikeBasedTopic[]
	 */
	private function getTopics( array $spec ) {
		$topics = [];
		foreach ( $spec as $topicId => $titleNames ) {
			$titleValues = [];
			foreach ( $titleNames as $titleName ) {
				$titleValues[] = new TitleValue( NS_MAIN, $titleName );
			}
			$topics[] = new MorelikeBasedTopic( $topicId, $titleValues );
		}
		return $topics;
	}

	/**
	 * Returns a PHPUnit invocation matcher which matches a range.
	 * @param $min
	 * @param $max
	 * @return InvokedRecorder
	 */
	private function exactlyBetween( $min, $max ) {
		return new class ( $min, $max ) extends InvokedRecorder {
			private $min;
			private $max;

			public function __construct( $min, $max ) {
				$this->min = $min;
				$this->max = $max;
			}

			public function toString(): string {
				return "invoked between $this->min and $this->max times";
			}

			public function verify() {
				$count = $this->getInvocationCount();
				if ( $count < $this->min || $count > $this->max ) {
					throw new ExpectationFailedException(
						"Expected to be invoked between $this->min and $this->max times,"
						. " but it occurred $count time(s)."
					);
				}
			}
		};
	}

	private function taskSetToArray( TaskSet $taskSet ) {
		return array_map( function ( Task $task ) {
			$taskData = [
				'taskType' => $task->getTaskType()->getId(),
				'titleNs' => $task->getTitle()->getNamespace(),
				'titleDbkey' => $task->getTitle()->getDBkey(),
				'topics' => array_map( function ( Topic $topic ) {
					return $topic->getId();
				}, $task->getTopics() ),
			];
			return $taskData;
		}, iterator_to_array( Util::getIteratorFromTraversable( $taskSet ) ) );
	}

}
