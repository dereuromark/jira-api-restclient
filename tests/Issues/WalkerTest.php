<?php

namespace Tests\Jira\Issues;

use Exception;
use Jira\Api;
use Jira\Api\Result;
use Jira\Api\UnauthorizedException;
use Jira\Issue;
use Jira\Issues\Walker;
use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\Polyfills\AssertStringContains;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;

class WalkerTest extends TestCase {

	use ExpectException, AssertStringContains;

	/**
	 * API.
	 *
	 * @var \Prophecy\Prophecy\ObjectProphecy|\Jira\Api
	 */
	protected $api;

	/**
	 * Error log file.
	 *
	 * @var string
	 */
	protected $errorLogFile;

	/**
	 * @before
	 * @return void
	 */
	protected function setUpTest() {
		$this->api = $this->prophesize(Api::class);

		if ($this->captureErrorLog()) {
			$this->errorLogFile = tempnam(sys_get_temp_dir(), 'error_log_');
			$this->assertEmpty(file_get_contents($this->errorLogFile));

			ini_set('error_log', $this->errorLogFile);
		}
	}

	/**
	 * @after
	 * @return void
	 */
	protected function tearDownTest() {
		if ($this->captureErrorLog()) {
			ini_restore('error_log');
			unlink($this->errorLogFile);
		}
	}

	/**
	 * Determines if contents of error log needs to be captured.
	 *
	 * @return bool
	 */
	protected function captureErrorLog() {
		return strpos($this->getName(false), 'AnyException') !== false;
	}

	/**
	 * @return void
	 */
	public function testErrorWithoutJQL() {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('you have to call Jira_Walker::push($jql, $fields) at first');

		foreach ($this->createWalker() as $issue) {
			echo '';
		}
	}

	/**
	 * @return void
	 */
	public function testFoundNoIssues() {
		$search_response = $this->generateSearchResponse('PRJ', 0);
		$this->api->search('test jql', 0, 5, 'description')->willReturn($search_response);

		$walker = $this->createWalker(5);
		$walker->push('test jql', 'description');

		$found_issues = [];

		foreach ($walker as $issue) {
			$found_issues[] = $issue;
		}

		$this->assertCount(0, $found_issues);
	}

	/**
	 * @return void
	 */
	public function testDefaultPerPageUsed() {
		$search_response = $this->generateSearchResponse('PRJ', 50);
		$this->api->search('test jql', 0, 50, 'description')->willReturn($search_response);

		$walker = $this->createWalker();
		$walker->push('test jql', 'description');

		$found_issues = [];

		foreach ($walker as $issue) {
			$found_issues[] = $issue;
		}

		$this->assertEquals(
			$search_response->getIssues(),
			$found_issues,
		);
	}

	/**
	 * @return void
	 */
	public function testFoundTwoPagesOfIssues() {
		// Full 1st page.
		$search_response1 = $this->generateSearchResponse('PRJ1', 5, 7);
		$this->api->search('test jql', 0, 5, 'description')->willReturn($search_response1);

		// Incomplete 2nd page.
		$search_response2 = $this->generateSearchResponse('PRJ2', 2, 7);
		$this->api->search('test jql', 5, 5, 'description')->willReturn($search_response2);

		$walker = $this->createWalker(5);
		$walker->push('test jql', 'description');

		$found_issues = [];

		foreach ($walker as $issue) {
			$found_issues[] = $issue;
		}

		$this->assertEquals(
			array_merge($search_response1->getIssues(), $search_response2->getIssues()),
			$found_issues,
		);
	}

	/**
	 * @return void
	 */
	public function testUnauthorizedExceptionOnFirstPage() {
		$this->expectException('\Jira\Api\UnauthorizedException');
		$this->expectExceptionMessage('Unauthorized');

		$this->api->search('test jql', 0, 5, 'description')->willThrow(new UnauthorizedException('Unauthorized'));

		$walker = $this->createWalker(5);
		$walker->push('test jql', 'description');

		foreach ($walker as $issue) {
			echo '';
		}
	}

	/**
	 * @return void
	 */
	public function testAnyExceptionOnFirstPage() {
		$this->api->search('test jql', 0, 5, 'description')->willThrow(new Exception('Anything'));

		$walker = $this->createWalker(5);
		$walker->push('test jql', 'description');

		foreach ($walker as $issue) {
			echo '';
		}

		$this->assertStringContainsString('Anything', file_get_contents($this->errorLogFile));
	}

	/**
	 * @return void
	 */
	public function testUnauthorizedExceptionOnSecondPage() {
		$this->expectException('\Jira\Api\UnauthorizedException');
		$this->expectExceptionMessage('Unauthorized');

		// Full 1st page.
		$search_response1 = $this->generateSearchResponse('PRJ1', 5, 7);
		$this->api->search('test jql', 0, 5, 'description')->willReturn($search_response1);

		// Incomplete 2nd page.
		$this->api->search('test jql', 5, 5, 'description')->willThrow(new UnauthorizedException('Unauthorized'));

		$walker = $this->createWalker(5);
		$walker->push('test jql', 'description');

		foreach ($walker as $issue) {
			echo '';
		}
	}

	/**
	 * @return void
	 */
	public function testAnyExceptionOnSecondPage() {
		// Full 1st page.
		$search_response1 = $this->generateSearchResponse('PRJ1', 5, 7);
		$this->api->search('test jql', 0, 5, 'description')->willReturn($search_response1);

		// Incomplete 2nd page.
		$this->api->search('test jql', 5, 5, 'description')->willThrow(new Exception('Anything'));

		$walker = $this->createWalker(5);
		$walker->push('test jql', 'description');

		foreach ($walker as $issue) {
			echo '';
		}

		$this->assertStringContainsString('Anything', file_get_contents($this->errorLogFile));
	}

	/**
	 * @return void
	 */
	public function testSetDelegateError() {
		$this->expectException('\Exception');
		$this->expectExceptionMessage('passed argument is not callable');

		$walker = $this->createWalker();
		$walker->setDelegate('not a callable');
	}

	/**
	 * @return void
	 */
	public function testIssuesPassedThroughDelegate() {
		$search_response = $this->generateSearchResponse('PRJ', 2);
		$this->api->search('test jql', 0, 2, 'description')->willReturn($search_response);

		$walker = $this->createWalker(2);
		$walker->push('test jql', 'description');
		$walker->setDelegate(function (Issue $issue) {
			return $issue->get('description');
		});

		$found_issues = [];

		foreach ($walker as $issue) {
			$found_issues[] = $issue;
		}

		$this->assertEquals(
			['description 2', 'description 1'],
			$found_issues,
		);
	}

	/**
	 * @return void
	 */
	public function testCounting() {
		// Full 1st page.
		$search_response1 = $this->generateSearchResponse('PRJ1', 5, 7);
		$this->api->search('test jql', 0, 5, 'description')->willReturn($search_response1);

		// Incomplete 2nd page.
		$search_response2 = $this->generateSearchResponse('PRJ2', 2, 7);
		$this->api->search('test jql', 5, 5, 'description')->willReturn($search_response2);

		$walker = $this->createWalker(5);
		$walker->push('test jql', 'description');

		$this->assertEquals(7, count($walker));

		$found_issues = [];

		foreach ($walker as $issue) {
			$found_issues[] = $issue;
		}

		$this->assertEquals(
			array_merge($search_response1->getIssues(), $search_response2->getIssues()),
			$found_issues,
		);
	}

	/**
	 * Generate search response.
	 *
	 * @param string $project_key Project key.
	 * @param int $issue_count Issue count.
	 * @param int|null $total Total issues.
	 *
	 * @return \Jira\Api\Result
	 */
	protected function generateSearchResponse($project_key, $issue_count, $total = null) {
		$issues = [];

		if (!is_numeric($total)) {
			$total = $issue_count;
		}

		while ($issue_count > 0) {
			$issue_id = $issue_count + 1000;
			$issues[] = [
				'expand' => 'operations,versionedRepresentations,editmeta,changelog,transitions,renderedFields',
				'id' => $issue_id,
				'self' => 'http://jira.company.com/rest/api/2/issue/' . $issue_id,
				'key' => $project_key . '-' . $issue_id,
				'fields' => [
					'description' => 'description ' . $issue_count,
				],
			];
			$issue_count--;
		}

		return new Result([
			'expand' => 'schema,names',
			'startAt' => 0,
			'maxResults' => count($issues),
			'total' => $total,
			'issues' => $issues,
		]);
	}

	/**
	 * Creates walker instance.
	 *
	 * @param int|null $per_page Per page.
	 *
	 * @return \Jira\Issues\Walker
	 */
	protected function createWalker($per_page = null) {
		return new Walker($this->api->reveal(), $per_page);
	}

}
