<?php

namespace Streamx\Clients\Ingestion\Utils;

use CloudEvents\Serializers\JsonDeserializer;
use CloudEvents\Serializers\JsonSerializer;
use CloudEvents\V1\CloudEventInterface;
use Streamx\Clients\Ingestion\Exceptions\StreamxClientException;
use TypeError;

class CloudEventsSerializer {

    public const CLOUD_EVENT_CONTENT_TYPE = "application/cloudevents+json";
    public const BATCH_CLOUD_EVENT_CONTENT_TYPE = "application/cloudevents-batch+json";

    private static ?JsonSerializer $jsonSerializer = null;
    private static ?JsonDeserializer $jsonDeserializer = null;

    /**
     * @param CloudEventInterface[] $cloudEvents
     */
    public static function serialize(array $cloudEvents): SerializedCloudEvents {
        try {
            return self::doSerialize($cloudEvents);
        } catch (TypeError $e) {
            // bug in JsonSerializer: internally it uses json_encode(), and that returns false (not a string) on deserialization error
            $errorMessage = 'Serialization error';
            $cause = json_last_error() === JSON_ERROR_NONE ? '' : ': ' . json_last_error_msg();
            throw new StreamxClientException($errorMessage . $cause);
        }
    }

    /**
     * @param CloudEventInterface[] $cloudEvents
     */
    private static function doSerialize(array $cloudEvents): SerializedCloudEvents {
        if (count($cloudEvents) == 1) {
            $json = self::getSerializer()->serializeStructured($cloudEvents[0]);
            return new SerializedCloudEvents($json, self::CLOUD_EVENT_CONTENT_TYPE);
        } else {
            $json = self::getSerializer()->serializeBatch($cloudEvents);
            return new SerializedCloudEvents($json, self::BATCH_CLOUD_EVENT_CONTENT_TYPE);
        }
    }

    /**
     * @return CloudEventInterface[]
     */
    public static function deserialize(string $json, string $contentType): array {
        if ($contentType === self::CLOUD_EVENT_CONTENT_TYPE) {
            return [self::getDeserializer()->deserializeStructured($json)];
        }
        if ($contentType === self::BATCH_CLOUD_EVENT_CONTENT_TYPE) {
            return self::getDeserializer()->deserializeBatch($json);
        }
        return [];
    }

    private static function getSerializer(): JsonSerializer {
        return self::$jsonSerializer ??= JsonSerializer::create();
    }

    private static function getDeserializer(): JsonDeserializer {
        return self::$jsonDeserializer ??= JsonDeserializer::create();
    }
}