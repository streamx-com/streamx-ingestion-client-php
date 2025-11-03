<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Tests\Testing;

use CloudEvents\V1\CloudEventInterface;
use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\RequestInfo;
use PHPUnit\Framework\TestCase;
use Streamx\Clients\Ingestion\Builders\StreamxClientBuilders;
use Streamx\Clients\Ingestion\Publisher\Publisher;
use Streamx\Clients\Ingestion\StreamxClient;
use Streamx\Clients\Ingestion\Utils\CloudEventsSerializer;

class MockServerTestCase extends TestCase
{
    protected static MockWebServer $server;
    protected StreamxClient $client;

    public static function setUpBeforeClass(): void
    {
        self::$server = new MockWebServer();
        self::$server->start();
    }

    protected function setUp(): void
    {
        $this->client = StreamxClientBuilders::create(self::$server->getServerRoot())->build();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    protected function createPublisher() : Publisher
    {
        return $this->client->newPublisher();
    }

    /**
     * @param CloudEventInterface[] $expectedCloudEvents
     */
    protected function assertIngestionRequest(
        RequestInfo $request,
        string $uri,
        array $expectedCloudEvents,
        array $headers = null
    ): void {
        $expectedSerializedEvents = CloudEventsSerializer::serialize($expectedCloudEvents);

        $this->assertEquals('POST', $request->getRequestMethod());
        $this->assertEquals($uri, $request->getRequestUri());
        $this->assertEquals($expectedSerializedEvents->getJson(), $request->getInput());
        $this->assertEquals($expectedSerializedEvents->getContentType(), $request->getHeaders()['Content-Type']);
        $this->assertHeaders($request, $headers);
    }

    private function assertHeaders(RequestInfo $request, ?array $expectedHeaders): void
    {
        if ($expectedHeaders) {
            $requestHeaders = $request->getHeaders();
            foreach ($expectedHeaders as $name => $value) {
                $this->assertEquals($value, $requestHeaders[$name]);
            }
        }
    }
}