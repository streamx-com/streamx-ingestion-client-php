<?php

namespace Streamx\Clients\Ingestion\Tests\Testing\Model;

use CloudEvents\V1\CloudEventImmutable;
use CloudEvents\V1\CloudEventInterface;
use DateTimeImmutable;

class CloudEventUtils {

    private const TYPE_PAGE_PUBLISHED = 'com.streamx.blueprints.page.published.v1';
    private const TYPE_PAGE_UNPUBLISHED = 'com.streamx.blueprints.page.unpublished.v1';

    private function __construct() {
    }

    public static function pagePublishEvent(string $subject, Page $page, ?int $time = null): CloudEventInterface {
        return self::event($subject, $page, $time, self::TYPE_PAGE_PUBLISHED);
    }

    public static function pageArrayPublishEvent(string $subject, array $page, ?int $time = null): CloudEventInterface {
        return self::event($subject, $page, $time, self::TYPE_PAGE_PUBLISHED);
    }

    public static function pageUnpublishEvent(string $subject, ?int $time = null): CloudEventInterface {
        return self::event($subject, null, $time, self::TYPE_PAGE_UNPUBLISHED);
    }

    /**
     * @param object|null|array $content
     */
    public static function event(string $subject, $content = null, ?int $time = null, string $eventType = 'generic.event'): CloudEventInterface {
        return new CloudEventImmutable(
            'id',
            'source',
            $eventType,
            $content,
            'application/json',
            null,
            $subject,
            $time ? (new DateTimeImmutable())->setTimestamp($time) : new DateTimeImmutable()
        );
    }

}