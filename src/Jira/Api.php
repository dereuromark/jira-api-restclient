<?php
/*
 * The MIT License
 *
 * Copyright (c) 2014 Shuhei Tanuma
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace chobie\Jira;

use chobie\Jira\Api\Authentication\AuthenticationInterface;
use chobie\Jira\Api\Client\ClientInterface;
use chobie\Jira\Api\Client\CurlClient;
use chobie\Jira\Api\Result;

class Api {

	/**
	 * @var string
	 */
	public const REQUEST_GET = 'GET';

	/**
	 * @var string
	 */
	public const REQUEST_POST = 'POST';

	/**
	 * @var string
	 */
	public const REQUEST_PUT = 'PUT';

	/**
	 * @var string
	 */
	public const REQUEST_DELETE = 'DELETE';

	/**
	 * @var int
	 */
	public const AUTOMAP_FIELDS = 0x01;

	/**
	 * Endpoint URL.
	 *
	 * @var string
	 */
	protected $endpoint;

	/**
	 * Client.
	 *
	 * @var \chobie\Jira\Api\Client\ClientInterface
	 */
	protected $client;

	/**
	 * Authentication.
	 *
	 * @var \chobie\Jira\Api\Authentication\AuthenticationInterface
	 */
	protected $authentication;

	/**
	 * Options.
	 *
	 * @var int
	 */
	protected $options = self::AUTOMAP_FIELDS;

	/**
	 * Client-side cache of fields. List of fields when loaded, null when nothing is fetched yet.
	 *
	 * @var array|null
	 */
	protected $fields;

	/**
	 * Client-side cache of priorities. List of priorities when loaded, null when nothing is fetched yet.
	 *
	 * @var array|null
	 */
	protected $priorities;

	/**
	 * Client-side cache of statuses. List of statuses when loaded, null when nothing is fetched yet.
	 *
	 * @var array|null
	 */
	protected $statuses;

	/**
	 * Client-side cache of resolutions. List of resolutions when loaded, null when nothing is fetched yet.
	 *
	 * @var array|null
	 */
	protected $resolutions;

	/**
	 * Create a Jira API client.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @param \chobie\Jira\Api\Authentication\AuthenticationInterface $authentication Authentication.
	 * @param \chobie\Jira\Api\Client\ClientInterface|null $client Client.
	 */
	public function __construct(
		$endpoint,
		AuthenticationInterface $authentication,
		?ClientInterface $client = null
	) {
		$this->setEndPoint($endpoint);
		$this->authentication = $authentication;

		if ($client === null) {
			$client = new CurlClient();
		}

		$this->client = $client;
	}

	/**
	 * Sets options.
	 *
	 * @param int $options Options.
	 *
	 * @return void
	 */
	public function setOptions($options) {
		$this->options = $options;
	}

	/**
	 * Get Endpoint URL.
	 *
	 * @return string
	 */
	public function getEndpoint() {
		return $this->endpoint;
	}

	/**
	 * Set Endpoint URL.
	 *
	 * @param string $url Endpoint URL.
	 *
	 * @return void
	 */
	public function setEndpoint($url) {
		// Remove trailing slash in the url.
		$url = rtrim($url, '/');

		if ($url !== $this->endpoint) {
			$this->endpoint = $url;
			$this->clearLocalCaches();
		}
	}

	/**
	 * Helper method to clear the local caches. Is called when switching endpoints
	 *
	 * @return void
	 */
	protected function clearLocalCaches() {
		$this->fields = null;
		$this->priorities = null;
		$this->statuses = null;
		$this->resolutions = null;
	}

	/**
	 * Get fields definitions.
	 *
	 * @return array
	 */
	public function getFields() {
		// Fetch fields when the method is called for the first time.
		if ($this->fields === null) {
			$fields = [];
			$result = $this->api(static::REQUEST_GET, '/rest/api/2/field', [], true);

			/* set hash key as custom field id */
			foreach ($result as $field) {
				$fields[$field['id']] = $field;
			}

			$this->fields = $fields;
		}

		return $this->fields;
	}

	/**
	 * Get specified issue.
	 *
	 * @param string $issueKey Issue key should be "YOURPROJ-221".
	 * @param string $expand Expand.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function getIssue($issueKey, $expand = '') {
		return $this->api(static::REQUEST_GET, sprintf('/rest/api/2/issue/%s', $issueKey), ['expand' => $expand]);
	}

	/**
	 * Edits the issue.
	 *
	 * @param string $issueKey Issue key.
	 * @param array $params Params.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function editIssue($issueKey, array $params = []) {
		return $this->api(static::REQUEST_PUT, sprintf('/rest/api/2/issue/%s', $issueKey), $params);
	}

	/**
	 * Gets attachments meta information.
	 *
	 * @return array
	 */
	public function getAttachmentsMetaInformation() {
		return $this->api(static::REQUEST_GET, '/rest/api/2/attachment/meta');
	}

	/**
	 * Gets attachment.
	 *
	 * @param string $attachment_id Attachment ID.
	 *
	 * @return array|false
	 */
	public function getAttachment($attachment_id) {
		return $this->api(static::REQUEST_GET, sprintf('/rest/api/2/attachment/%s', $attachment_id), [], true);
	}

	/**
	 * Returns all projects.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function getProjects() {
		return $this->api(static::REQUEST_GET, '/rest/api/2/project');
	}

	/**
	 * Returns one project.
	 *
	 * @param string $project_key Project key.
	 *
	 * @return array|false
	 */
	public function getProject($project_key) {
		return $this->api(static::REQUEST_GET, sprintf('/rest/api/2/project/%s', $project_key), [], true);
	}

	/**
	 * Returns all roles of a project.
	 *
	 * @param string $project_key Project key.
	 *
	 * @return array|false
	 */
	public function getRoles($project_key) {
		return $this->api(static::REQUEST_GET, sprintf('/rest/api/2/project/%s/role', $project_key), [], true);
	}

	/**
	 * Returns role details.
	 *
	 * @param string $project_key Project key.
	 * @param string $role_id Role ID.
	 *
	 * @return array|false
	 */
	public function getRoleDetails($project_key, $role_id) {
		return $this->api(
			static::REQUEST_GET,
			sprintf('/rest/api/2/project/%s/role/%s', $project_key, $role_id),
			[],
			true,
		);
	}

	/**
	 * Returns the meta data for creating issues.
	 * This includes the available projects, issue types
	 * and fields, including field types and whether or not those fields are required.
	 * Projects will not be returned if the user does not have permission to create issues in that project.
	 * Fields will only be returned if "projects.issuetypes.fields" is added as expand parameter.
	 *
	 * @param array|null $project_ids Combined with the projectKeys param, lists the projects with which to filter the
	 *                                results. If absent, all projects are returned. Specifying a project that does not
	 *                                exist (or that you cannot create issues in) is not an error, but it will not be
	 *                                in the results.
	 * @param array|null $project_keys Combined with the projectIds param, lists the projects with which to filter the
	 *                                results. If null, all projects are returned. Specifying a project that does not
	 *                                exist (or that you cannot create issues in) is not an error, but it will not be
	 *                                in the results.
	 * @param array|null $issue_type_ids Combined with issuetypeNames, lists the issue types with which to filter the
	 *                                results. If null, all issue types are returned. Specifying an issue type that
	 *                                does not exist is not an error.
	 * @param array|null $issue_type_names Combined with issuetypeIds, lists the issue types with which to filter the
	 *                                results. If null, all issue types are returned. This parameter can be specified
	 *                                multiple times, but is NOT interpreted as a comma-separated list. Specifying
	 *                                an issue type that does not exist is not an error.
	 * @param array|null $expand Optional list of entities to expand in the response.
	 *
	 * @return array|false
	 */
	public function getCreateMeta(
		?array $project_ids = null,
		?array $project_keys = null,
		?array $issue_type_ids = null,
		?array $issue_type_names = null,
		?array $expand = null
	) {
		// Create comma separated query parameters for the supplied filters.
		$data = [];

		if ($project_ids !== null) {
			$data['projectIds'] = implode(',', $project_ids);
		}

		if ($project_keys !== null) {
			$data['projectKeys'] = implode(',', $project_keys);
		}

		if ($issue_type_ids !== null) {
			$data['issuetypeIds'] = implode(',', $issue_type_ids);
		}

		if ($issue_type_names !== null) {
			$data['issuetypeNames'] = implode(',', $issue_type_names);
		}

		if ($expand !== null) {
			$data['expand'] = implode(',', $expand);
		}

		return $this->api(static::REQUEST_GET, '/rest/api/2/issue/createmeta', $data, true);
	}

	/**
	 * Add a comment to a ticket.
	 *
	 * @param string $issueKey Issue key should be "YOURPROJ-221".
	 * @param array|string $params Params.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function addComment($issueKey, $params) {
		if (is_string($params)) {
			// If $params is scalar string value -> wrapping it properly.
			$params = [
				'body' => $params,
			];
		}

		return $this->api(static::REQUEST_POST, sprintf('/rest/api/2/issue/%s/comment', $issueKey), $params);
	}

	/**
	 * Gets all worklogs for an issue.
	 *
	 * @param string $issueKey Issue key should be "YOURPROJ-22".
	 * @param array $params Params.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function getWorklogs($issueKey, array $params = []) {
		return $this->api(static::REQUEST_GET, sprintf('/rest/api/2/issue/%s/worklog', $issueKey), $params);
	}

	/**
	 * Creates Jira Worklog
	 *
	 * @param string $issueKey
	 * @param string $time
	 * @param array $params
	 * @return array
	 */
	public function createWorklog($issueKey, $time, array $params = []) {
		$params = [
			'timeSpent' => $time,
		] + $params;

		return $this->api(static::REQUEST_POST, sprintf('/rest/api/2/issue/%s/worklog', $issueKey), $params);
	}

	/**
	 * Removes a Jira Worklog
	 *
	 * @param string $issueKey
	 * @param string $worklogId
	 * @param array $params
	 * @return array
	 */
	public function removeWorklog($issueKey, $worklogId, array $params = []) {
		$params = [
			'adjustEstimate' => 'auto',
		] + $params;

		return $this->api(static::REQUEST_DELETE, sprintf('/rest/api/2/issue/%s/worklog/%s', $issueKey, $worklogId), $params);
	}

	/**
	 * Get available transitions for a ticket.
	 *
	 * @param string $issueKey Issue key should be "YOURPROJ-22".
	 * @param array $params Params.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function getTransitions($issueKey, array $params = []) {
		return $this->api(static::REQUEST_GET, sprintf('/rest/api/2/issue/%s/transitions', $issueKey), $params);
	}

	/**
	 * Transition a ticket.
	 *
	 * @param string $issueKey Issue key should be "YOURPROJ-22".
	 * @param array $params Params.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function transition($issueKey, array $params = []) {
		return $this->api(static::REQUEST_POST, sprintf('/rest/api/2/issue/%s/transitions', $issueKey), $params);
	}

	/**
	 * Get available issue types.
	 *
	 * @return array<IssueType>
	 */
	public function getIssueTypes() {
		$result = [];
		$types = $this->api(static::REQUEST_GET, '/rest/api/2/issuetype', [], true);

		foreach ($types as $type) {
			$result[] = new IssueType($type);
		}

		return $result;
	}

	/**
	 * Get versions of a project.
	 *
	 * @param string $project_key Project key.
	 *
	 * @return array|false
	 */
	public function getVersions($project_key) {
		return $this->api(static::REQUEST_GET, sprintf('/rest/api/2/project/%s/versions', $project_key), [], true);
	}

	/**
	 * Helper method to find a specific version based on the name of the version.
	 *
	 * @param string $project_key Project Key.
	 * @param string $name The version name to match on.
	 *
	 * @return array|null Version data on match or null when there is no match.
	 */
	public function findVersionByName($project_key, $name) {
		// Fetch all versions of this project.
		$versions = $this->getVersions($project_key);

		// Filter results on the name.
		$matching_versions = array_filter($versions, function (array $version) use ($name) {
			return $version['name'] == $name;
		});

		// Early out for no results.
		if (empty($matching_versions)) {
			return null;
		}

		// Multiple results should not happen since name is unique.
		return reset($matching_versions);
	}

	/**
	 * Get available priorities.
	 *
	 * @return array
	 */
	public function getPriorities() {
		// Fetch priorities when the method is called for the first time.
		if ($this->priorities === null) {
			$priorities = [];
			$result = $this->api(static::REQUEST_GET, '/rest/api/2/priority', [], true);

			/* set hash key as custom field id */
			foreach ($result as $priority) {
				$priorities[$priority['id']] = $priority;
			}

			$this->priorities = $priorities;
		}

		return $this->priorities;
	}

	/**
	 * Get available statuses.
	 *
	 * @return array
	 */
	public function getStatuses() {
		// Fetch statuses when the method is called for the first time.
		if ($this->statuses === null) {
			$statuses = [];
			$result = $this->api(static::REQUEST_GET, '/rest/api/2/status', [], true);

			/* set hash key as custom field id */
			foreach ($result as $status) {
				$statuses[$status['id']] = $status;
			}

			$this->statuses = $statuses;
		}

		return $this->statuses;
	}

	/**
	 * Creates an issue.
	 *
	 * @param string $project_key Project key.
	 * @param string $summary Summary.
	 * @param string $issue_type Issue type.
	 * @param array $options Options.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function createIssue($project_key, $summary, $issue_type, array $options = []) {
		$default = [
			'project' => [
				'key' => $project_key,
			],
			'summary' => $summary,
			'issuetype' => [
				'id' => $issue_type,
			],
		];

		$default = array_merge($default, $options);

		return $this->api(static::REQUEST_POST, '/rest/api/2/issue/', ['fields' => $default]);
	}

	/**
	 * Query issues.
	 *
	 * @param string $jql JQL.
	 * @param int $start_at Start at.
	 * @param int $max_results Max results.
	 * @param string $fields Fields.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function search($jql, $start_at = 0, $max_results = 20, $fields = '*navigable') {
		$result = $this->api(
			static::REQUEST_GET,
			'/rest/api/2/search',
			[
				'jql' => $jql,
				'startAt' => $start_at,
				'maxResults' => $max_results,
				'fields' => $fields,
			],
		);

		return $result;
	}

	/**
	 * Creates new version.
	 *
	 * @param string $project_key Project key.
	 * @param string $version Version.
	 * @param array $options Options.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function createVersion($project_key, $version, array $options = []) {
		$options = array_merge(
			[
				'name' => $version,
				'description' => '',
				'project' => $project_key,
				// 'userReleaseDate' => '',
				// 'releaseDate' => '',
				'released' => false,
				'archived' => false,
			],
			$options,
		);

		return $this->api(static::REQUEST_POST, '/rest/api/2/version', $options);
	}

	/**
	 * Updates version.
	 *
	 * @link https://docs.atlassian.com/jira/REST/latest/#api/2/version-updateVersion
	 * @param int $version_id Version ID.
	 * @param array $params Key->Value list to update the version with.
	 *
	 * @return false
	 */
	public function updateVersion($version_id, array $params = []) {
		return $this->api(static::REQUEST_PUT, sprintf('/rest/api/2/version/%d', $version_id), $params);
	}

	/**
	 * Shorthand to mark a version as Released.
	 *
	 * @param int $version_id Version ID.
	 * @param string|null $release_date Date in Y-m-d format (defaults to today).
	 * @param array $params Optionally extra parameters.
	 *
	 * @return false
	 */
	public function releaseVersion($version_id, $release_date = null, array $params = []) {
		if (!$release_date) {
			$release_date = date('Y-m-d');
		}

		$params = array_merge(
			[
				'releaseDate' => $release_date,
				'released' => true,
			],
			$params,
		);

		return $this->updateVersion($version_id, $params);
	}

	/**
	 * Create attachment.
	 *
	 * @param string $issueKey Issue key.
	 * @param string $filename Filename.
	 * @param string|null $name Name.
	 *
	 * @return \chobie\Jira\Api\Result|false
	 */
	public function createAttachment($issueKey, $filename, $name = null) {
		$options = [
			'file' => '@' . $filename,
			'name' => $name,
		];

		return $this->api(
			static::REQUEST_POST,
			sprintf('/rest/api/2/issue/%s/attachments', $issueKey),
			$options,
			false,
			true,
		);
	}

	/**
	 * Creates a remote link.
	 *
	 * @param string $issueKey Issue key.
	 * @param array $object Object.
	 * @param string|null $relationship Relationship.
	 * @param string|null $global_id Global ID.
	 * @param array|null $application Application.
	 *
	 * @return array|false
	 */
	public function createRemoteLink(
		$issueKey,
		array $object = [],
		$relationship = null,
		$global_id = null,
		?array $application = null
	) {
		$options = [
						'globalId' => $global_id,
						'relationship' => $relationship,
						'object' => $object,
					];

		if ($application !== null) {
			$options['application'] = $application;
		}

		return $this->api(static::REQUEST_POST, sprintf('/rest/api/2/issue/%s/remotelink', $issueKey), $options, true);
	}

	/**
	 * Send request to specified host.
	 *
	 * @param string $method Request method.
	 * @param string $url URL.
	 * @param array|string $data Data.
	 * @param bool $return_as_array Return results as associative array.
	 * @param bool $is_file Is file-related request.
	 * @param bool $debug Debug this request.
	 *
	 * @return \chobie\Jira\Api\Result|array|false
	 */
	public function api(
		$method,
		$url,
		$data = [],
		$return_as_array = false,
		$is_file = false,
		$debug = false
	) {
		$result = $this->client->sendRequest(
			$method,
			$url,
			$data,
			$this->getEndpoint(),
			$this->authentication,
			$is_file,
			$debug,
		);

		if (strlen($result)) {
			$json = json_decode($result, true);

			if ($this->options & static::AUTOMAP_FIELDS) {
				if (isset($json['issues'])) {
					if ($this->fields === null) {
						$this->getFields();
					}

					foreach ($json['issues'] as $offset => $issue) {
						$json['issues'][$offset] = $this->automapFields($issue);
					}
				}
			}

			if ($return_as_array) {
				return $json;
			}

			return new Result($json);
		}

			return false;
	}

	/**
	 * Downloads attachment.
	 *
	 * @param string $url URL.
	 *
	 * @return array|string
	 */
	public function downloadAttachment($url) {
		$result = $this->client->sendRequest(
			static::REQUEST_GET,
			$url,
			[],
			'',
			$this->authentication,
			true,
			false,
		);

		return $result;
	}

	/**
	 * Automaps issue fields.
	 *
	 * @param array $issue Issue.
	 *
	 * @return array
	 */
	protected function automapFields(array $issue) {
		if (isset($issue['fields'])) {
			$x = [];

			foreach ($issue['fields'] as $kk => $vv) {
				if (isset($this->fields[$kk])) {
					$x[$this->fields[$kk]['name']] = $vv;
				} else {
					$x[$kk] = $vv;
				}
			}

			$issue['fields'] = $x;
		}

		return $issue;
	}

	/**
	 * Set issue watchers.
	 *
	 * @param string $issueKey Issue key.
	 * @param array $watchers Watchers.
	 *
	 * @return array<Result|false>
	 */
	public function setWatchers($issueKey, array $watchers) {
		$result = [];

		foreach ($watchers as $watcher) {
			$result[] = $this->api(static::REQUEST_POST, sprintf('/rest/api/2/issue/%s/watchers', $issueKey), $watcher);
		}

		return $result;
	}

	/**
	 * Closes issue.
	 *
	 * @TODO: Should have parameters? (e.g comment)
	 * @param string $issueKey Issue key.
	 *
	 * @return \chobie\Jira\Api\Result|array
	 */
	public function closeIssue($issueKey) {
		$result = [];

		// Get available transitions.
		$tmp_transitions = $this->getTransitions($issueKey, []);
		$tmp_transitions_result = $tmp_transitions->getResult();
		$transitions = $tmp_transitions_result['transitions'];

		// Look for "Close Issue" transition in issue transitions.
		foreach ($transitions as $v) {
			// Close issue if required id was found.
			if ($v['name'] == 'Close Issue') {
				$result = $this->transition(
					$issueKey,
					[
						'transition' => ['id' => $v['id']],
					],
				);

				break;
			}
		}

		return $result;
	}

	/**
	 * Returns project components.
	 *
	 * @param string $project_key Project key.
	 *
	 * @return array
	 */
	public function getProjectComponents($project_key) {
		return $this->api(static::REQUEST_GET, sprintf('/rest/api/2/project/%s/components', $project_key), [], true);
	}

	/**
	 * Get all issue types with valid status values for a project.
	 *
	 * @param string $project_key Project key.
	 *
	 * @return array
	 */
	public function getProjectIssueTypes($project_key) {
		return $this->api(static::REQUEST_GET, sprintf('/rest/api/2/project/%s/statuses', $project_key), [], true);
	}

	/**
	 * Returns a list of all resolutions.
	 *
	 * @return array
	 */
	public function getResolutions() {
		// Fetch resolutions when the method is called for the first time.
		if ($this->resolutions === null) {
			$resolutions = [];
			$result = $this->api(static::REQUEST_GET, '/rest/api/2/resolution', [], true);

			foreach ($result as $resolution) {
				$resolutions[$resolution['id']] = $resolution;
			}

			$this->resolutions = $resolutions;
		}

		return $this->resolutions;
	}

}
