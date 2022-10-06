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

namespace chobie\Jira\Issues;

use chobie\Jira\Api;
use chobie\Jira\Api\Result;
use Exception;

class Walker implements \Iterator, \Countable {

	/**
	 * API.
	 *
	 * @var \chobie\Jira\Api
	 */
	protected $api;

	/**
	 * JQL.
	 *
	 * @var string|null
	 */
	protected $jql;

	/**
	 * Offset.
	 *
	 * @var int
	 */
	protected $offset = 0;

	/**
	 * Current record index.
	 *
	 * @var int
	 */
	protected $current = 0;

	/**
	 * Total issue count.
	 *
	 * @var int|null
	 */
	protected $total;

	/**
	 * Issue count on current page.
	 *
	 * @var int
	 */
	protected $max = 0;

	/**
	 * Index of issue in issue list (across all issue pages).
	 *
	 * @var int
	 */
	protected $startAt = 0;

	/**
	 * Issues per page.
	 *
	 * @var int
	 */
	protected $perPage = 50;

	/**
	 * Was JQL executed.
	 *
	 * @var bool
	 */
	protected $executed = false;

	/**
	 * Result.
	 *
	 * @var array
	 */
	protected $issues = [];

	/**
	 * List of fields to query.
	 *
	 * @var array|string|null
	 */
	protected $fields;

	/**
	 * Callback.
	 *
	 * @var callable|null
	 */
	protected $callback;

	/**
	 * Creates walker instance.
	 *
	 * @param \chobie\Jira\Api $api API.
	 * @param int|null $per_page Per page.
	 */
	public function __construct(Api $api, $per_page = null) {
		$this->api = $api;

		if (is_numeric($per_page)) {
			$this->perPage = $per_page;
		}
	}

	/**
	 * Pushes JQL.
	 *
	 * @param string $jql JQL.
	 * @param array|string|null $fields Fields.
	 *
	 * @return void
	 */
	public function push($jql, $fields = null) {
		$this->jql = $jql;
		$this->fields = $fields;
	}

	/**
	 * Return the current element.
	 *
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return mixed Can return any type.
	 */
	#[\ReturnTypeWillChange]
	public function current() {
		if (is_callable($this->callback)) {
			$tmp = $this->issues[$this->offset];
			$callback = $this->callback;

			return $callback($tmp);
		}

		return $this->issues[$this->offset];
	}

	/**
	 * Move forward to next element.
	 *
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 */
	#[\ReturnTypeWillChange]
	public function next() {
		$this->offset++;
	}

	/**
	 * Return the key of the current element.
	 *
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return mixed scalar on success, or null on failure.
	 */
	#[\ReturnTypeWillChange]
	public function key() {
		if ($this->startAt > 0) {
			return $this->offset + (($this->startAt - 1) * $this->perPage);
		}

			return 0;
	}

	/**
	 * Checks if current position is valid.
	 *
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @throws \Exception When "Walker::push" method wasn't called.
	 * @throws Api\UnauthorizedException When it happens.
	 * @return bool The return value will be casted to boolean and then evaluated.
 * Returns true on success or false on failure.
	 */
	#[\ReturnTypeWillChange]
	public function valid() {
		if ($this->jql === null) {
			throw new Exception('you have to call Jira_Walker::push($jql, $fields) at first');
		}

		if (!$this->executed) {
			try {
				$result = $this->api->search($this->getQuery(), $this->key(), $this->perPage, $this->fields);

				$this->setResult($result);
				$this->executed = true;

				if ($result->getTotal() == 0) {
					return false;
				}

				return true;
			}
			catch (Api\UnauthorizedException $e) {
				throw $e;
			}
			catch (\Exception $e) {
				error_log($e->getMessage());

				return false;
			}
		} else {
			if ($this->offset >= $this->max && $this->key() < $this->total) {
				try {
					$result = $this->api->search($this->getQuery(), $this->key(), $this->perPage, $this->fields);
					$this->setResult($result);

					return true;
				}
				catch (Api\UnauthorizedException $e) {
					throw $e;
				}
				catch (\Exception $e) {
					error_log($e->getMessage());

					return false;
				}
			} else {
				if (($this->startAt - 1) * $this->perPage + $this->offset < $this->total) {
					return true;
				}

				return false;
			}
		}
	}

	/**
	 * Rewind the Iterator to the first element.
	 *
	 * @link http://php.net/manual/en/iterator.rewind.php
	 * @return void Any returned value is ignored.
	 */
	#[\ReturnTypeWillChange]
	public function rewind() {
		$this->offset = 0;
		$this->startAt = 0;
		$this->current = 0;
		$this->max = 0;
		$this->total = null;
		$this->executed = false;
		$this->issues = [];
	}

	/**
	 * Count elements of an object.
	 *
	 * @link http://php.net/manual/en/countable.count.php
	 * @return int The custom count as an integer.
	 */
	#[\ReturnTypeWillChange]
	public function count() {
		if ($this->total === null) {
			$this->valid();
		}

		return $this->total;
	}

	/**
	 * Sets callable.
	 *
	 * @param callable|null $callable Callable.
	 *
	 * @throws \Exception When not a callable passed.
	 * @return void
	 */
	public function setDelegate($callable) {
		if (!is_callable($callable)) {
			throw new Exception('passed argument is not callable');
		}

		$this->callback = $callable;
	}

	/**
	 * Sets result.
	 *
	 * @param \chobie\Jira\Api\Result $result Result.
	 *
	 * @return void
	 */
	protected function setResult(Result $result) {
		$this->total = $result->getTotal();
		$this->offset = 0;
		$this->max = $result->getIssuesCount();
		$this->issues = $result->getIssues();
		$this->startAt++;
	}

	/**
	 * Returns JQL.
	 *
	 * @return string
	 */
	protected function getQuery() {
		return $this->jql;
	}

}
