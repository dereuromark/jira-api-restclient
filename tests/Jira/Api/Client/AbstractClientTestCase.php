<?php

namespace Tests\chobie\Jira\Api\Client;

use chobie\Jira\Api;
use chobie\Jira\Api\Authentication\Anonymous;
use chobie\Jira\Api\Authentication\AuthenticationInterface;
use chobie\Jira\Api\Authentication\Basic;
use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;

abstract class AbstractClientTestCase extends TestCase {

	use ExpectException;

	/**
	 * Client.
	 *
	 * @var \chobie\Jira\Api\Client\ClientInterface
	 */
	protected $client;

	/**
	 * @before
	 * @return void
	 */
	protected function setUpTest() {
		if (empty($_SERVER['REPO_URL'])) {
			$this->markTestSkipped('The "REPO_URL" environment variable not set.');
		}

		$this->client = $this->createClient();
	}

	/**
	 * @dataProvider getRequestWithKnownHttpCodeDataProvider
	 *
	 * @param string $http_code
	 *
	 * @return void
	 */
	public function testGetRequestWithKnownHttpCode($http_code) {
		$data = ['param1' => 'value1', 'param2' => 'value2'];
		$trace_result = $this->traceRequest(Api::REQUEST_GET, array_merge(['http_code' => $http_code], $data));

		$this->assertEquals('GET', $trace_result['_SERVER']['REQUEST_METHOD']);
		$this->assertContentType('application/json;charset=UTF-8', $trace_result);
		$this->assertEquals($data, $trace_result['_GET']);
	}

	/**
	 * @return array<array<int>>
	 */
	public function getRequestWithKnownHttpCodeDataProvider() {
		return [
			'http 200' => [200],
			'http 403' => [403],
		];
	}

	/**
	 * @return void
	 */
	public function testGetRequestError() {
		$this->expectException('\InvalidArgumentException');
		$this->expectExceptionMessage('Data must be an array.');

		$this->traceRequest(Api::REQUEST_GET, 'param1=value1&param2=value2');
	}

	/**
	 * @return void
	 */
	public function testPostRequest() {
		$data = ['param1' => 'value1', 'param2' => 'value2'];
		$trace_result = $this->traceRequest(Api::REQUEST_POST, $data);

		$this->assertEquals('POST', $trace_result['_SERVER']['REQUEST_METHOD']);
		$this->assertContentType('application/json;charset=UTF-8', $trace_result);
		$this->assertEquals(json_encode($data), $trace_result['INPUT']);
	}

	/**
	 * @return void
	 */
	public function testPutRequest() {
		$data = ['param1' => 'value1', 'param2' => 'value2'];
		$trace_result = $this->traceRequest(Api::REQUEST_PUT, $data);

		$this->assertEquals('PUT', $trace_result['_SERVER']['REQUEST_METHOD']);
		$this->assertContentType('application/json;charset=UTF-8', $trace_result);
		$this->assertEquals(json_encode($data), $trace_result['INPUT']);
	}

	/**
	 * @return void
	 */
	public function testDeleteRequest() {
		$data = ['param1' => 'value1', 'param2' => 'value2'];
		$trace_result = $this->traceRequest(Api::REQUEST_DELETE, $data);

		$this->assertEquals('DELETE', $trace_result['_SERVER']['REQUEST_METHOD']);
		$this->assertContentType('application/json;charset=UTF-8', $trace_result);
		$this->assertEquals(json_encode($data), $trace_result['INPUT']);
	}

	/**
	 * @dataProvider fileUploadDataProvider
	 *
	 * @param string $filename
	 * @param string $name
	 *
	 * @return void
	 */
	public function testFileUpload($filename, $name) {
		$upload_file = $filename;
		$data = ['file' => '@' . $upload_file, 'name' => $name];
		$trace_result = $this->traceRequest(Api::REQUEST_POST, $data, null, true);

		$this->assertEquals('POST', $trace_result['_SERVER']['REQUEST_METHOD']);

		$this->assertArrayHasKey('HTTP_X_ATLASSIAN_TOKEN', $trace_result['_SERVER']);
		$this->assertEquals('nocheck', $trace_result['_SERVER']['HTTP_X_ATLASSIAN_TOKEN']);

		$this->assertCount(
			1,
			$trace_result['_FILES'],
			'File was uploaded',
		);
		$this->assertArrayHasKey(
			'file',
			$trace_result['_FILES'],
			'File was uploaded under "file" field name',
		);
		$this->assertEquals(
			($name !== null) ? $name : basename($upload_file),
			$trace_result['_FILES']['file']['name'],
			'Filename is as expected',
		);
		$this->assertNotEmpty($trace_result['_FILES']['file']['type']);
		$this->assertEquals(
			UPLOAD_ERR_OK,
			$trace_result['_FILES']['file']['error'],
			'No upload error happened',
		);
		$this->assertGreaterThan(
			0,
			$trace_result['_FILES']['file']['size'],
			'File is not empty',
		);
	}

	/**
	 * @return array<array>
	 */
	public function fileUploadDataProvider() {
		return [
			'default name' => ['file' => __FILE__, 'name' => null],
			'overridden name' => ['file' => __FILE__, 'name' => 'custom_name.php'],
		];
	}

	/**
	 * @return void
	 */
	public function testUnsupportedCredentialGiven() {
		$client_class_parts = explode('\\', get_class($this->client));
		$credential = $this->prophesize('chobie\Jira\Api\Authentication\AuthenticationInterface')->reveal();

		if (\method_exists($this, 'setExpectedException')) {
			$this->setExpectedException(
				'InvalidArgumentException',
				end($client_class_parts) . ' does not support ' . get_class($credential) . ' authentication.',
			);
		} else {
			$this->expectException('InvalidArgumentException');
			$this->expectExceptionMessage(
				end($client_class_parts) . ' does not support ' . get_class($credential) . ' authentication.',
			);
		}

		$this->client->sendRequest(Api::REQUEST_GET, 'url', [], 'endpoint', $credential);
	}

	/**
	 * @return void
	 */
	public function testBasicCredentialGiven() {
		$credential = new Basic('user1', 'pass1');

		$trace_result = $this->traceRequest(Api::REQUEST_GET, [], $credential);

		$this->assertArrayHasKey('PHP_AUTH_USER', $trace_result['_SERVER']);
		$this->assertEquals('user1', $trace_result['_SERVER']['PHP_AUTH_USER']);

		$this->assertArrayHasKey('PHP_AUTH_PW', $trace_result['_SERVER']);
		$this->assertEquals('pass1', $trace_result['_SERVER']['PHP_AUTH_PW']);
	}

	/**
	 * @return void
	 */
	public function testCommunicationError() {
		$this->markTestIncomplete('TODO');
	}

	/**
	 * @return void
	 */
	public function testUnauthorizedRequest() {
		$this->expectException('\chobie\Jira\Api\UnauthorizedException');
		$this->expectExceptionMessage('Unauthorized');

		$this->traceRequest(Api::REQUEST_GET, ['http_code' => 401]);
	}

	/**
	 * @return void
	 */
	public function testEmptyResponseWithUnknownHttpCode() {
		$this->expectException('\chobie\Jira\Api\Exception');
		$this->expectExceptionMessage('JIRA Rest server returns unexpected result.');

		$this->traceRequest(Api::REQUEST_GET, ['response_mode' => 'empty']);
	}

	/**
	 * @dataProvider emptyResponseWithKnownHttpCodeDataProvider
	 *
	 * @param string $http_code
	 *
	 * @return void
	 */
	public function testEmptyResponseWithKnownHttpCode($http_code) {
		$this->assertSame(
			'',
			$this->traceRequest(Api::REQUEST_GET, ['http_code' => $http_code, 'response_mode' => 'empty']),
		);
	}

	/**
	 * @return array<array<int>>
	 */
	public function emptyResponseWithKnownHttpCodeDataProvider() {
		return [
			'http 201' => [201],
			'http 204' => [204],
		];
	}

	/**
	 * Checks, that request contained specified content type.
	 *
	 * @param string $expected Expected.
	 * @param array $trace_result Trace result.
	 *
	 * @return void
	 */
	protected function assertContentType($expected, array $trace_result) {
		if (array_key_exists('CONTENT_TYPE', $trace_result['_SERVER'])) {
			// Normal Web Server.
			$content_type = $trace_result['_SERVER']['CONTENT_TYPE'];
		} elseif (array_key_exists('HTTP_CONTENT_TYPE', $trace_result['_SERVER'])) {
			// PHP Built-In Web Server.
			$content_type = $trace_result['_SERVER']['HTTP_CONTENT_TYPE'];
		} else {
			$content_type = null;
		}

		$this->assertEquals($expected, $content_type, 'Content type is correct');
	}

	/**
	 * Traces a request.
	 *
	 * @param string $method Request method.
	 * @param array $data Request data.
	 * @param \chobie\Jira\Api\Authentication\AuthenticationInterface|null $credential Credential.
	 * @param bool $is_file This is a file upload request.
	 *
	 * @return array
	 */
	protected function traceRequest(
		$method,
		$data = [],
		?AuthenticationInterface $credential = null,
		$is_file = false
	) {
		if (!isset($credential)) {
			$credential = new Anonymous();
		}

		$path_info_variables = [
			'http_code' => 200,
			'response_mode' => 'trace',
		];

		if (is_array($data)) {
			if (isset($data['http_code'])) {
				$path_info_variables['http_code'] = $data['http_code'];
				unset($data['http_code']);
			}

			if (isset($data['response_mode'])) {
				$path_info_variables['response_mode'] = $data['response_mode'];
				unset($data['response_mode']);
			}
		}

		$path_info = [];

		foreach ($path_info_variables as $variable_name => $variable_value) {
			$path_info[] = $variable_name;
			$path_info[] = $variable_value;
		}

		$result = $this->client->sendRequest(
			$method,
			'/tests/debug_response.php/' . implode('/', $path_info) . '/',
			$data,
			rtrim($_SERVER['REPO_URL'], '/'),
			$credential,
			$is_file,
		);

		if ($path_info_variables['response_mode'] === 'trace') {
			return unserialize($result);
		}

		return $result;
	}

	/**
	 * Creates client.
	 *
	 * @return \chobie\Jira\Api\Client\ClientInterface
	 */
	abstract protected function createClient();

}
