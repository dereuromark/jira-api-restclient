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

namespace Jira\Api\Client;

use Jira\Api\Authentication\AuthenticationInterface;

interface ClientInterface {

	/**
	 * Sends request to the API server.
	 *
	 * @param string $method Request method.
	 * @param string $url URL.
	 * @param array|string $data Request data.
	 * @param string $endpoint Endpoint.
	 * @param \Jira\Api\Authentication\AuthenticationInterface $credential Credential.
	 * @param bool $is_file This is a file upload request.
	 * @param bool $debug Debug this request.
	 *
	 * @throws \InvalidArgumentException When non-supported implementation of AuthenticationInterface is given.
	 * @throws \InvalidArgumentException When data is not an array and http method is GET.
	 * @throws \Jira\Api\Exception When request failed due communication error.
	 * @throws \Jira\Api\UnauthorizedException When request failed, because user can't be authorized properly.
	 * @throws \Jira\Api\Exception When there was empty response instead of needed data.
	 * @return array|string
	 */
	public function sendRequest(
		$method,
		$url,
		$data,
		$endpoint,
		AuthenticationInterface $credential,
		$is_file = false,
		$debug = false
	);

}
