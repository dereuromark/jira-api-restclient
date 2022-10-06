<?php

namespace Tests\Jira\Api\Client;

use Jira\Api\Client\PHPClient;

class PHPClientTest extends AbstractClientTestCase {

	/**
	 * Creates client.
	 *
	 * @return \Jira\Api\Client\ClientInterface
	 */
	protected function createClient() {
		return new PHPClient();
	}

}
