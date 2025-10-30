<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Tests\Testing;

use CloudEvents\V1\CloudEventInterface;
use donatj\MockWebServer\Response;
use Streamx\Clients\Ingestion\Utils\CloudEventsSerializer;

class StreamxResponse
{
    public static function success(CloudEventInterface $event): Response
    {
        return self::successes([$event]);
    }

    /**
     * @param CloudEventInterface[] $events
     */
    public static function successes(array $events): Response
    {
        return self::responseWithCloudEvents(202, $events);
    }

    /**
     * @param CloudEventInterface[] $events
     */
    public static function responseWithCloudEvents(int $statusCode, array $events): Response
    {
        $serializedEvents = CloudEventsSerializer::serialize($events);
        return new Response(
            $serializedEvents->getJson(),
            ['Content-Type' => $serializedEvents->getContentType()],
            $statusCode
        );
    }

    public static function failure(int $statusCode, string $responseText): Response
    {
        return new Response(
            $responseText,
            ['Content-Type' => 'text/plain'],
            $statusCode
        );
    }
}