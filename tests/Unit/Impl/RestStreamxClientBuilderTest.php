<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Tests\Unit\Impl;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Streamx\Clients\Ingestion\Builders\StreamxClientBuilders;
use Streamx\Clients\Ingestion\Tests\Testing\CloudEventsComparator;
use Streamx\Clients\Ingestion\Tests\Testing\Impl\CustomTestHttpRequester;
use Streamx\Clients\Ingestion\Tests\Testing\MockServerTestCase;
use Streamx\Clients\Ingestion\Tests\Testing\Model\CloudEventUtils;
use Streamx\Clients\Ingestion\Tests\Testing\Model\Page;
use Streamx\Clients\Ingestion\Tests\Testing\StreamxResponse;
use Streamx\Clients\Ingestion\Utils\CloudEventsSerializer;

class RestStreamxClientBuilderTest extends MockServerTestCase
{

    /** @test */
    public function shouldSetCustomIngestionEndpointUri()
    {
        // Given
        $page = new Page('test');
        $event = CloudEventUtils::pagePublishEvent('key', $page, 836383);

        self::$server->setResponseOfPath('/custom-ingestion/v2',
            StreamxResponse::success($event));

        $this->client = StreamxClientBuilders::create(self::$server->getServerRoot())
            ->setIngestionEndpointPath('/custom-ingestion/v2')
            ->build();

        // When
        $result = $this->createPublisher()->send($event);

        // Then
        $this->assertIngestionRequest(self::$server->getLastRequest(),
            '/custom-ingestion/v2',
            [$event]);

        CloudEventsComparator::assertSameEvents($result, $event);
    }

    /** @test */
    public function shouldSetCustomHttpRequester()
    {
        // Given
        $page = new Page('custom requester');
        $event = CloudEventUtils::pagePublishEvent('key', $page, 937493);

        self::$server->setResponseOfPath('/custom-requester-ingestion/v1',
            StreamxResponse::success($event));

        $this->client = StreamxClientBuilders::create(self::$server->getServerRoot())
            ->setHttpRequester(new CustomTestHttpRequester('/custom-requester-ingestion/v1'))
            ->build();

        // When
        $result = $this->createPublisher()->send($event);

        // Then
        $this->assertIngestionRequest(self::$server->getLastRequest(),
            '/custom-requester-ingestion/v1',
            [$event]);

        CloudEventsComparator::assertSameEvents($result, $event);
    }

    /** @test */
    public function shouldSetCustomHttpClient()
    {
        // Given
        $page = new Page('custom http client');
        $event = CloudEventUtils::pagePublishEvent('key', $page, 937493);

        $serializedEvents = CloudEventsSerializer::serialize([$event]);
        $responseJson = $serializedEvents->getJson();
        $contentType = $serializedEvents->getContentType();

        $responseBodyMock = $this->createMock(StreamInterface::class);
        $responseBodyMock->method('getContents')->willReturn($responseJson);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(202);
        $responseMock->method('getBody')->willReturn($responseBodyMock);
        $responseMock->method('hasHeader')->with('Content-Type')->willReturn(true);
        $responseMock->method('getHeader')->with('Content-Type')->willReturn([$contentType]);

        $clientMock = $this->createMock(Client::class);
        $clientMock->method('request')->willReturnCallback(function($method, $uri, $options) use ($contentType, $responseMock)
        {
            $options = [
                'http' => [
                    'method'  => $method,
                    'header'  => "Content-Type: $contentType\r\nX-StreamX: Custom http client",
                    'content' => $options[RequestOptions::BODY],
                ],
            ];

            $context = stream_context_create($options);

            $url = self::$server->getServerRoot() . '/ingestion/v2/cloudevents';
            file_get_contents($url, false, $context); // perform the HTTP POST request

            return $responseMock;
        });

        $this->client = StreamxClientBuilders::create(self::$server->getServerRoot())
            ->setHttpClient($clientMock)
            ->build();

        // When
        $result = $this->createPublisher()->send($event);

        // Then
        $this->assertIngestionRequest(self::$server->getLastRequest(),
            '/ingestion/v2/cloudevents',
            [$event],
            ['X-StreamX' => 'Custom http client']);

        CloudEventsComparator::assertSameEvents($result, $event);
    }
}