<?php

namespace Tests\Jira\Api\Client;

use Jira\Api\Client\CurlClient;

class CurlClientTest extends AbstractClientTestCase {

	/**
	 * Creates client.
	 *
	 * @return \Jira\Api\Client\ClientInterface
	 */
	protected function createClient() {
		return new CurlClient();
	}

}
