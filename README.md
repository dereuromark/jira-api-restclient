# Jira REST API Client

[![CI](https://github.com/dereuromark/jira-api-restclient/actions/workflows/ci.yml/badge.svg)](https://github.com/dereuromark/jira-api-restclient/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg?style=flat)](https://phpstan.org/)
[![Latest Stable Version](https://poser.pugx.org/dereuromark/jira-api-restclient/v/stable.svg)](https://packagist.org/packages/dereuromark/jira-api-restclient)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-8892BF.svg)](https://php.net/)
[![License](https://poser.pugx.org/dereuromark/jira-api-restclient/license.png)](https://packagist.org/packages/dereuromark/jira-api-restclient)

You all know that Jira supports REST API, right? It can be very useful, for example, during automation job creation and notification sending.

This library will ensure unforgettable experience when working with Jira through REST API. Hope you'll enjoy it.

## Usage

* Jira REST API Documents: https://docs.atlassian.com/jira/REST/latest/

```php
<?php
use Jira\Api;
use Jira\Api\Authentication\Basic;
use Jira\Issues\Walker;

$api = new Api(
    'https://your-jira-project.net',
    new Basic('yourname', 'password')
);

$walker = new Walker($api);
$walker->push(
	'project = "YOURPROJECT" AND (status != "closed" AND status != "resolved") ORDER BY priority DESC'
);

foreach ( $walker as $issue ) {
    var_dump($issue);
    // Send custom notification here.
}
```

## Installation

```
php composer.phar require dereuromark/jira-api-restclient
```

## Requirements

* [Composer](https://getcomposer.org/download/)

## License

Jira REST API Client is released under the MIT License. See the bundled [LICENSE](LICENSE) file for details.
