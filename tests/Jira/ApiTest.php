<?php

namespace Tests\chobie\Jira;

use chobie\Jira\Api;
use chobie\Jira\Api\Result;
use PHPUnit\Framework\TestCase;

/**
 * Class ApiTest
 */
class ApiTest extends TestCase {

	/**
	 * @var string
	 */
	public const ENDPOINT = 'http://jira.company.com';

	/**
	 * Api.
	 *
	 * @var \chobie\Jira\Api
	 */
	protected $api;

	/**
	 * Credential.
	 *
	 * @var \chobie\Jira\Api\Authentication\AuthenticationInterface
	 */
	protected $credential;

	/**
	 * Client.
	 *
	 * @var \Prophecy\Prophecy\ObjectProphecy
	 */
	protected $client;

	/**
	 * @before
	 * @return void
	 */
	protected function setUpTest() {
		$this->credential = $this->prophesize('chobie\Jira\Api\Authentication\AuthenticationInterface')->reveal();
		$this->client = $this->prophesize('chobie\Jira\Api\Client\ClientInterface');

		$this->api = new Api(static::ENDPOINT, $this->credential, $this->client->reveal());
	}

	/**
	 * @dataProvider setEndpointDataProvider
	 *
	 * @param string $given_endpoint
	 * @param string $used_endpoint
	 *
	 * @return void
	 */
	public function testSetEndpoint($given_endpoint, $used_endpoint) {
		$api = new Api($given_endpoint, $this->credential, $this->client->reveal());
		$this->assertEquals($used_endpoint, $api->getEndpoint());
	}

	/**
	 * @return array<array<string>>
	 */
	public function setEndpointDataProvider() {
		return [
			'trailing slash removed' => ['https://test.test/', 'https://test.test'],
			'nothing removed' => ['https://test.test', 'https://test.test'],
		];
	}

	/**
	 * @return void
	 */
	public function testSearch() {
		$response = file_get_contents(__DIR__ . '/resources/api_search.json');

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/search',
			[
				'jql' => 'test',
				'startAt' => 0,
				'maxResults' => 2,
				'fields' => 'description',
			],
			$response,
		);

		$response_decoded = json_decode($response, true);

		// Field auto-expanding would trigger this call.
		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/field',
			[],
			file_get_contents(__DIR__ . '/resources/api_field.json'),
		);

		$this->assertEquals(new Result($response_decoded), $this->api->search('test', 0, 2, 'description'));
	}

	/**
	 * @return void
	 */
	public function testUpdateVersion() {
		$params = [
			'overdue' => true,
			'description' => 'new description',
		];

		$this->expectClientCall(
			Api::REQUEST_PUT,
			'/rest/api/2/version/111000',
			$params,
			'',
		);

		$this->assertFalse($this->api->updateVersion(111000, $params));
	}

	/**
	 * @return void
	 */
	public function testReleaseVersionAutomaticReleaseDate() {
		$params = [
			'released' => true,
			'releaseDate' => date('Y-m-d'),
		];

		$this->expectClientCall(
			Api::REQUEST_PUT,
			'/rest/api/2/version/111000',
			$params,
			'',
		);

		$this->assertFalse($this->api->releaseVersion(111000));
	}

	/**
	 * @return void
	 */
	public function testReleaseVersionParameterMerging() {
		$release_date = '2010-07-06';

		$expected_params = [
			'released' => true,
			'releaseDate' => $release_date,
			'test' => 'extra',
		];

		$this->expectClientCall(
			Api::REQUEST_PUT,
			'/rest/api/2/version/111000',
			$expected_params,
			'',
		);

		$this->assertFalse($this->api->releaseVersion(111000, $release_date, ['test' => 'extra']));
	}

	/**
	 * @return void
	 */
	public function testFindVersionByName() {
		$project_key = 'POR';
		$version_id = '14206';
		$version_name = '3.36.0';

		$versions = [
			['id' => '14205', 'name' => '3.62.0'],
			['id' => $version_id, 'name' => $version_name],
			['id' => '14207', 'name' => '3.66.0'],
		];

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/project/' . $project_key . '/versions',
			[],
			json_encode($versions),
		);

		$this->assertEquals(
			['id' => $version_id, 'name' => $version_name],
			$this->api->findVersionByName($project_key, $version_name),
			'Version found',
		);

		$this->assertNull(
			$this->api->findVersionByName($project_key, 'i_do_not_exist'),
		);
	}

	/**
	 * @return void
	 */
	public function testGetResolutions() {
		$response = file_get_contents(__DIR__ . '/resources/api_resolution.json');

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/resolution',
			[],
			$response,
		);

		$actual = $this->api->getResolutions();

		$response_decoded = json_decode($response, true);

		$expected = [
			'1' => $response_decoded[0],
			'10000' => $response_decoded[1],
		];
		$this->assertEquals($expected, $actual);

		// Second time we call the method the results should be cached and not trigger an API Request.
		$this->client->sendRequest(Api::REQUEST_GET, '/rest/api/2/resolution', [], static::ENDPOINT, $this->credential)
			->shouldNotBeCalled();
		$this->assertEquals($expected, $this->api->getResolutions(), 'Calling twice did not yield the same results');
	}

	/**
	 * @return void
	 */
	public function testGetFields() {
		$response = file_get_contents(__DIR__ . '/resources/api_field.json');

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/field',
			[],
			$response,
		);

		$actual = $this->api->getFields();

		$response_decoded = json_decode($response, true);

		$expected = [
			'issuetype' => $response_decoded[0],
			'timespent' => $response_decoded[1],
		];
		$this->assertEquals($expected, $actual);

		// Second time we call the method the results should be cached and not trigger an API Request.
		$this->client->sendRequest(Api::REQUEST_GET, '/rest/api/2/field', [], static::ENDPOINT, $this->credential)
			->shouldNotBeCalled();
		$this->assertEquals($expected, $this->api->getFields(), 'Calling twice did not yield the same results');
	}

	/**
	 * @return void
	 */
	public function testGetStatuses() {
		$response = file_get_contents(__DIR__ . '/resources/api_status.json');

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/status',
			[],
			$response,
		);

		$actual = $this->api->getStatuses();

		$response_decoded = json_decode($response, true);

		$expected = [
			'1' => $response_decoded[0],
			'3' => $response_decoded[1],
		];
		$this->assertEquals($expected, $actual);

		// Second time we call the method the results should be cached and not trigger an API Request.
		$this->client->sendRequest(Api::REQUEST_GET, '/rest/api/2/status', [], static::ENDPOINT, $this->credential)
			->shouldNotBeCalled();
		$this->assertEquals($expected, $this->api->getStatuses(), 'Calling twice did not yield the same results');
	}

	/**
	 * @return void
	 */
	public function testGetPriorities() {
		$response = file_get_contents(__DIR__ . '/resources/api_priority.json');

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/priority',
			[],
			$response,
		);

		$actual = $this->api->getPriorities();

		$response_decoded = json_decode($response, true);

		$expected = [
			'1' => $response_decoded[0],
			'5' => $response_decoded[1],
		];
		$this->assertEquals($expected, $actual);

		// Second time we call the method the results should be cached and not trigger an API Request.
		$this->client->sendRequest(Api::REQUEST_GET, '/rest/api/2/priority', [], static::ENDPOINT, $this->credential)
			->shouldNotBeCalled();
		$this->assertEquals($expected, $this->api->getPriorities(), 'Calling twice did not yield the same results');
	}

	/**
	 * @return void
	 */
	public function testGetIssue() {
		$response = file_get_contents(__DIR__ . '/resources/api_get_issue.json');

		$issue_key = 'POR-1';

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/issue/' . $issue_key,
			['expand' => ''],
			$response,
		);

		$actual = $this->api->getIssue($issue_key);

		$expected = json_decode($response, true);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @return void
	 */
	public function testEditIssue() {
		$issue_key = 'POR-1';
		$params = ['update' => (object)['summary' => [(object)['set' => 'Bug in business logic']]]];
		$this->expectClientCall(
			Api::REQUEST_PUT,
			'/rest/api/2/issue/' . $issue_key,
			$params,
			false, // False is returned because there is no content (204).
		);

		$actual = $this->api->editIssue($issue_key, $params);
		$this->assertEquals(false, $actual);
	}

	/**
	 * @return void
	 */
	public function testGetAttachmentsMetaInformation() {
		$response = file_get_contents(__DIR__ . '/resources/api_get_attachments_meta.json');

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/attachment/meta',
			[],
			$response,
		);

		$actual = $this->api->getAttachmentsMetaInformation();

		$expected = json_decode($response, true);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @return void
	 */
	public function testGetAttachment() {
		$response = file_get_contents(__DIR__ . '/resources/api_get_attachment.json');
		$attachment_id = '18700';

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/attachment/' . $attachment_id,
			[],
			$response,
		);

		$actual = $this->api->getAttachment($attachment_id);

		$expected = json_decode($response, true);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @return void
	 */
	public function testGetProjects() {
		$response = file_get_contents(__DIR__ . '/resources/api_get_projects.json');

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/project',
			[],
			$response,
		);

		$actual = $this->api->getProjects();

		$expected = json_decode($response, true);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @return void
	 */
	public function testGetProject() {
		$response = file_get_contents(__DIR__ . '/resources/api_get_project.json');
		$project_id = '10500';

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/project/' . $project_id,
			[],
			$response,
		);

		$actual = $this->api->getProject($project_id);

		$expected = json_decode($response, true);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @return void
	 */
	public function testGetRoles() {
		$response = file_get_contents(__DIR__ . '/resources/api_get_roles_of_project.json');
		$project_id = '10500';

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/project/' . $project_id . '/role',
			[],
			$response,
		);

		$actual = $this->api->getRoles($project_id);

		$expected = json_decode($response, true);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @return void
	 */
	public function testGetRoleDetails() {
		$response = file_get_contents(__DIR__ . '/resources/api_get_role_of_project_by_id.json');
		$project_id = '10500';
		$role_id = '10200';

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/project/' . $project_id . '/role/' . $role_id,
			[],
			$response,
		);

		$actual = $this->api->getRoleDetails($project_id, $role_id);

		$expected = json_decode($response, true);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @return void
	 */
	public function testAddComment() {
		$response = file_get_contents(__DIR__ . '/resources/api_add_comment.json');
		$issue_key = 'POR-1';

		// Testing the case where the description is provided directly instead of params
		$description = 'testdesc';

		$this->expectClientCall(
			Api::REQUEST_POST,
			'/rest/api/2/issue/' . $issue_key . '/comment',
			['body' => $description],
			$response,
		);

		$actual = $this->api->addComment($issue_key, $description);

		$expected = json_decode($response, true);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @return void
	 */
	public function testGetWorklog() {
		$response = file_get_contents(__DIR__ . '/resources/api_get_roles_of_project.json');
		$issue_key = 'POR-1';

		$this->expectClientCall(
			Api::REQUEST_GET,
			'/rest/api/2/issue/' . $issue_key . '/worklog',
			[],
			$response,
		);

		$actual = $this->api->getWorklogs($issue_key);

		$expected = json_decode($response, true);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * Expects a particular client call.
	 *
	 * @param string $method Request method.
	 * @param string $url URL.
	 * @param array|string $data Request data.
	 * @param string $return_value Return value.
	 * @param bool $is_file This is a file upload request.
	 * @param bool $debug Debug this request.
	 *
	 * @return void
	 */
	protected function expectClientCall(
		$method,
		$url,
		$data,
		$return_value,
		$is_file = false,
		$debug = false
	) {
		$this->client
			->sendRequest($method, $url, $data, static::ENDPOINT, $this->credential, $is_file, $debug)
			->willReturn($return_value)
			->shouldBeCalled();
	}

}
