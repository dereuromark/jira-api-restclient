<?php

namespace Tests\Jira;

use Jira\IssueType;
use PHPUnit\Framework\TestCase;

class IssueTypeTest extends TestCase {

	/**
	 * @return void
	 */
	public function testHandlesSingleIssueTypeWithAvatarId() {
		$issueTypeSource = [
		'self' => 'https://hosted.atlassian.net/rest/api/2/issuetype/4',
		'id' => '4',
		'description' => 'An improvement or enhancement to an existing feature or task.',
		'iconUrl' => 'https://hosted.atlassian.net/secure/viewavatar?size=xsmall&avatarId=1&avatarType=issuetype',
		'name' => 'Improvement',
		'subtask' => false,
		'avatarId' => 1,
		];
		$issueType = new IssueType($issueTypeSource);
		$this->assertEquals($issueType->getId(), $issueTypeSource['id']);
		$this->assertEquals($issueType->getDescription(), $issueTypeSource['description']);
		$this->assertEquals($issueType->getIconUrl(), $issueTypeSource['iconUrl']);
		$this->assertEquals($issueType->getName(), $issueTypeSource['name']);
		$this->assertEquals($issueType->getAvatarId(), $issueTypeSource['avatarId']);
	}

	/**
	 * @return void
	 */
	public function testHandlesSingleIssueTypeWithoutAvatarId() {
		$issueTypeSource = [
			'self' => 'https://hosted.atlassian.net/rest/api/2/issuetype/4',
			'id' => '4',
			'description' => 'An improvement or enhancement to an existing feature or task.',
			'iconUrl' => 'https://hosted.atlassian.net/secure/viewavatar?size=xsmall&avatarId=1&avatarType=issuetype',
			'name' => 'Improvement',
			'subtask' => false,
		];
		$issueType = new IssueType($issueTypeSource);
		$this->assertEquals($issueType->getId(), $issueTypeSource['id']);
		$this->assertEquals($issueType->getDescription(), $issueTypeSource['description']);
		$this->assertEquals($issueType->getIconUrl(), $issueTypeSource['iconUrl']);
		$this->assertEquals($issueType->getName(), $issueTypeSource['name']);
	}

}
