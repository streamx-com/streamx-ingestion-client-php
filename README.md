# StreamX PHP Ingestion Client

StreamX PHP Ingestion Client enables sending CloudEvents to StreamX via its
REST Ingestion Service.

# Requirements
PHP 7.4 or higher

# Compatibility
The Client is compatible with StreamX 2.x and StreamX Blueprints 3.x

# Main entry points:

- [StreamxClientBuilders](src/Builders/StreamxClientBuilders.php) - with which it's possible to
  create default or customized clients,
- [StreamxClient](src/StreamxClient.php) - with which it's possible to create publishers
- [Publisher](src/Publisher/Publisher.php) - with which it's possible to make actual ingestions of CloudEvents

# Example usage:

```php
<?php
// Start your local StreamX instance.
// The sample assumes rest ingestion service is available at port 8080 and web server sink at port 8081.

// Create test page to be published to StreamX. It can be created as an associative array:
$page = ['content' => base64_encode('Hello, StreamX!')];

// It can also be created as a PHP object that follows the same schema:
class Page {
    public string $content;

    public function __construct(string $content) {
        $this->content = base64_encode($content);
    }
}
$page = new Page('Hello, StreamX!');

// Note: in both approaches, the content has to be in Base64 format.

// Create the client and a publisher dedicated to a specific channel:
$ingestionClient = \Streamx\Clients\Ingestion\Builders\StreamxClientBuilders::create('http://localhost:8080')->build();
$publisher = $ingestionClient->newPublisher();

// Publish page
$key = '/pages/page1.html';
$publishPageEvent = new \CloudEvents\V1\CloudEventImmutable(
    'publish-event',
    'my-source',
    'com.streamx.blueprints.page.published.v1',
    $page,
    'application/json',
    null, // data schema
    $key
);
$publisher->send($publishPageEvent);
// Now the page should be available at http://localhost:8081/pages/page1.html

// Then, unpublish the page (data is not needed)
$unpublishPageEvent = new \CloudEvents\V1\CloudEventImmutable(
    'unpublish-event',
    'my-source',
    'com.streamx.blueprints.page.unpublished.v1',
    null, // data
    null, // data content type
    null, // data schema
    $key
);
$publisher->send($unpublishPageEvent);
// Now the page should be unpublished from http://localhost:8081/pages/page1.html
```

# Installation

The recommended way to install the client is through
[Composer](https://getcomposer.org/).

```bash
composer require streamx/ingestion-client-php
```

# Run tests with coverage

1. Install xdebug (with version that supports PHP 7.4):
```bash
pecl install xdebug-3.1.5
```

2. Configure xdebug mode:
```bash
export XDEBUG_MODE=coverage
```

3. Run tests with coverage and open results in web browser:
```bash
./vendor/bin/phpunit --coverage-text --coverage-html target/coverage-report
open target/coverage-report/index.html
```