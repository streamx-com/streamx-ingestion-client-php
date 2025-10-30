<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Tests\Unit\Impl;

use donatj\MockWebServer\Response;
use donatj\MockWebServer\ResponseStack;
use Exception;
use GuzzleHttp\RequestOptions;
use Streamx\Clients\Ingestion\Builders\StreamxClientBuilders;
use Streamx\Clients\Ingestion\Exceptions\StreamxClientException;
use Streamx\Clients\Ingestion\Tests\Testing\CloudEventsComparator;
use Streamx\Clients\Ingestion\Tests\Testing\MockServerTestCase;
use Streamx\Clients\Ingestion\Tests\Testing\Model\CloudEventUtils;
use Streamx\Clients\Ingestion\Tests\Testing\Model\Page;
use Streamx\Clients\Ingestion\Tests\Testing\StreamxResponse;
use Streamx\Clients\Ingestion\Utils\CloudEventsSerializer;

class RestStreamxClientIngestionTest extends MockServerTestCase
{
    private const LAST_REQUEST_OFFSET = -1;

    /** @test */
    public function shouldPublishDataAsObject()
    {
        // Given
        $data = new NestedData(new Data('Data name', 'Data description'),
            '<div style="margin:20px;">Data property</div>{"key":"value"}');
        $event = CloudEventUtils::event("key-to-publish", $data, 123456);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::success($event));

        // When
        $result = $this->createPublisher()->send($event);

        // Then
        $this->assertIngestionRequest(self::$server->getLastRequest(),
            '/ingestion/v2/cloudevents',
            [$event]);

        CloudEventsComparator::assertSameEvents($result, $event);
    }

    /** @test */
    public function shouldPublishDataAsAnArray()
    {
        // Given
        $data = ['content' => ['bytes' => 'Text']];
        $event = CloudEventUtils::event('data-as-array', $data, 100232);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::success($event));

        // When
        $result = $this->createPublisher()->send($event);

        // Then
        $this->assertIngestionRequest(self::$server->getLastRequest(),
            '/ingestion/v2/cloudevents',
            [$event]);

        CloudEventsComparator::assertSameEvents($result, $event);
    }

    /** @test */
    public function shouldMakeIngestionsWithAuthorizationToken()
    {
        // Given
        $publishKey = "publish-with-token";
        $unpublishKey = "unpublish-with-token";
        $publishEvent = CloudEventUtils::pagePublishEvent($publishKey, new Page('Test page'), 100211);
        $unpublishEvent = CloudEventUtils::pageUnpublishEvent($unpublishKey, 100212);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            new ResponseStack(
                StreamxResponse::success($publishEvent),
                StreamxResponse::success($unpublishEvent)));

        $this->client = StreamxClientBuilders::create(self::$server->getServerRoot())
            ->setAuthToken('abc-100')
            ->build();

        // When
        $this->createPublisher()->send($publishEvent);
        $this->createPublisher()->send($unpublishEvent);

        // Then
        $this->assertIngestionRequest(self::$server->getRequestByOffset($this::LAST_REQUEST_OFFSET - 1),
            '/ingestion/v2/cloudevents',
            [$publishEvent],
            ['Authorization' => 'Bearer abc-100']);

        $this->assertIngestionRequest(self::$server->getLastRequest(),
            '/ingestion/v2/cloudevents',
            [$unpublishEvent],
            ['Authorization' => 'Bearer abc-100']);
    }

    /** @test */
    public function shouldSendStringInUtf8Encoding()
    {
        // Given
        $data = ['message' => '¡Hola, 🌍!'];
        $event = CloudEventUtils::event('utf8', $data, 100298);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::success($event));

        // When
        $result = $this->createPublisher()->send($event);

        // Then
        $this->assertIngestionRequest(self::$server->getLastRequest(),
            '/ingestion/v2/cloudevents',
            [$event]);

        CloudEventsComparator::assertSameEvents($result, $event);
    }

    /** @test */
    public function shouldAllowOverwritingRequestOptions()
    {
        // Given
        $data = new Data('Hello World');
        $event = CloudEventUtils::event('key', $data, 123);
        $overwrittenEvent = CloudEventUtils::event('other-key', new Page("Different content"));

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::success($event));

        // When
        $result = $this->createPublisher()->send($event, [
            RequestOptions::TIMEOUT => 60,
            RequestOptions::BODY => CloudEventsSerializer::serialize([$overwrittenEvent])->getJson()
        ]);

        // Then
        $this->assertIngestionRequest(self::$server->getLastRequest(),
            '/ingestion/v2/cloudevents',
            [$overwrittenEvent]);

        CloudEventsComparator::assertSameEvents($result, $event);
    }

    /** @test */
    public function shouldThrowExceptionWhenDataNotInUtf8Encoding()
    {
        // Given
        $data = ['message' => mb_convert_encoding('¡Hola, 🌍!', 'ISO-8859-1', 'UTF-8')];
        $event = CloudEventUtils::event("latin-1", $data);

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Serialization error: Malformed UTF-8 characters, possibly incorrectly encoded');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldThrowExceptionWhenInvalidIngestionInput()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data);

        self::$server->setResponseOfPath("/ingestion/v2/cloudevents",
            StreamxResponse::failure(400, 'Invalid data.'));

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Bad request. Invalid data.');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldThrowExceptionWhenForbiddenAccess()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data);

        self::$server->setResponseOfPath("/ingestion/v2/cloudevents",
            StreamxResponse::failure(403, 'No access.'));

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Forbidden. No access.');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldThrowExceptionWhenUnexpectedServerErrorWithResponseEvents()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data, 1);
        $responseEvent = CloudEventUtils::event('key', '{"error-type": "OUT_OF_MEMORY"}', 1);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::responseWithCloudEvents(500, [$responseEvent]));

        try {
            // When
            $this->createPublisher()->send($event);
            $this->fail('Expected an exception to be thrown');
        } catch (Exception $ex) {
            // Then
            $this->assertInstanceOf(StreamxClientException::class, $ex);
            $this->assertEquals('Unexpected server error. ', $ex->getMessage());
            $this->assertEquals([$responseEvent], $ex->getResponseEvents());
        }
    }

    /** @test */
    public function shouldThrowExceptionWhenUnexpectedServerErrorWithoutResponseEvents()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::failure(500, 'Out of memory.'));

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Unexpected server error. Out of memory.');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldThrowExceptionWhenServiceUnavailable()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::failure(503, 'Too many requests.'));

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Service unavailable. Too many requests.');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldHandleMultiMessageRequestsAndResponses()
    {
        // Given
        $inputEvents = [
            CloudEventUtils::pagePublishEvent('key-1', new Page('Page 1')),
            CloudEventUtils::pageUnpublishEvent('key-2'),
            CloudEventUtils::pagePublishEvent('key-3', new Page('Page 3')),
            CloudEventUtils::pageUnpublishEvent('key-4'),
            CloudEventUtils::pagePublishEvent('key-5', new Page('Page 5'))
        ];

        $mockResponseEvents = [
            CloudEventUtils::event('key-1'),
            CloudEventUtils::event('key-2'),
            CloudEventUtils::event('key-3'),
            CloudEventUtils::event('key-4'),
            CloudEventUtils::event('key-5')
        ];

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::successes($mockResponseEvents));

        // When
        $resultEvents = $this->createPublisher()->sendMulti($inputEvents);

        // Then
        CloudEventsComparator::assertSameEvents($resultEvents[0], $mockResponseEvents[0]);
        CloudEventsComparator::assertSameEvents($resultEvents[1], $mockResponseEvents[1]);
        CloudEventsComparator::assertSameEvents($resultEvents[2], $mockResponseEvents[2]);
        CloudEventsComparator::assertSameEvents($resultEvents[3], $mockResponseEvents[3]);
        CloudEventsComparator::assertSameEvents($resultEvents[4], $mockResponseEvents[4]);
    }

    /** @test */
    public function shouldThrowExceptionWhenSuccessButNoResponseEvents()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::failure(202, 'failure'));

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Success response contains no response events');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldThrowExceptionWhenUnknownResponseModel()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            new Response(
                '{ "this-is-not" : "a-valid-cloud-event" }',
                ['Content-Type' => CloudEventsSerializer::CLOUD_EVENT_CONTENT_TYPE],
                202
            ));

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Error while parsing body of response with HTTP status 202. Unsupported CloudEvent spec version.');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldThrowExceptionWhenUndefinedServerError()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::failure(408, 'Request to this server timed out'));

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Communication error. Response status: 408. Message: Request Timeout');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldThrowExceptionWhenUnauthorizedError()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data);

        self::$server->setResponseOfPath('/ingestion/v2/cloudevents',
            StreamxResponse::failure(401, 'You are unauthorized.'));

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Authentication failed. Make sure that the given token is valid.');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldThrowExceptionWhenNonExistingHost()
    {
        // Given
        $data = new Data('Test name');
        $event = CloudEventUtils::event('key', $data);

        $this->client = StreamxClientBuilders::create('https://non-existing')->build();

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Ingestion POST request with URI: ' .
            'https://non-existing/ingestion/v2/cloudevents failed due to HTTP client error');

        // When
        $this->createPublisher()->send($event);
    }

    /** @test */
    public function shouldThrowExceptionWhenRelativeUrl()
    {
        // Given
        $serverUrl = 'relative/url';

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Ingestion endpoint URI: relative/url/ingestion/v2/cloudevents is malformed. ' .
            'Relative URI is not supported.');

        // When
        StreamxClientBuilders::create($serverUrl)->build()->newPublisher();
    }

    /** @test */
    public function shouldThrowExceptionWhenUrlWithoutHost()
    {
        // Given
        $serverUrl = 'https:///url-without-host';

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Ingestion endpoint URI: https:///url-without-host/ingestion/v2/cloudevents is malformed. ' .
            'Unable to parse URI: https:///url-without-host/ingestion/v2/cloudevents');

        // When
        StreamxClientBuilders::create($serverUrl)->build()->newPublisher();
    }

    /** @test */
    public function shouldThrowExceptionWhenMalformedUrl()
    {
        // Given
        $serverUrl = ':malformed-uri/path';

        // Expect exception
        $this->expectException(StreamxClientException::class);
        $this->expectExceptionMessage('Ingestion endpoint URI: :malformed-uri/path/ingestion/v2/cloudevents is malformed. ' .
            'Relative URI is not supported.');

        // When
        StreamxClientBuilders::create($serverUrl)->build()->newPublisher();
    }
}

class NestedData
{
    public Data $data;
    public string $property;

    public function __construct(Data $data, string $property)
    {
        $this->data = $data;
        $this->property = $property;
    }
}

class Data
{
    public string $name;
    public ?string $description;

    public function __construct(string $name, ?string $description = null)
    {
        $this->name = $name;
        $this->description = $description;
    }

}