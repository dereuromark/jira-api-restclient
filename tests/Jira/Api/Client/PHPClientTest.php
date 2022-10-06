<?php

namespace Tests\chobie\Jira\Api\Client;

use chobie\Jira\Api\Client\PHPClient;

class PHPClientTest extends AbstractClientTestCase {

	/**
	 * Creates client.
	 *
	 * @return \chobie\Jira\Api\Client\ClientInterface
	 */
	protected function createClient() {
		return new PHPClient();
	}

}
