<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Tests\Testing;

use CloudEvents\V1\CloudEventInterface;
use PHPUnit\Framework\Assert;

class CloudEventsComparator
{
    public static function assertSameEvents(CloudEventInterface $actual, CloudEventInterface $expected): void
    {
        Assert::assertEquals($actual->getId(), $expected->getId());
        Assert::assertEquals($actual->getSource(), $expected->getSource());
        Assert::assertEquals($actual->getType(), $expected->getType());
        Assert::assertEquals(json_encode($actual->getData()), json_encode($expected->getData()));
        Assert::assertEquals($actual->getDataContentType(), $expected->getDataContentType());
        Assert::assertEquals($actual->getDataSchema(), $expected->getDataSchema());
        Assert::assertEquals($actual->getSubject(), $expected->getSubject());
        Assert::assertEquals($actual->getTime()->format('Y-m-d H:i:s'), $expected->getTime()->format('Y-m-d H:i:s'));
        Assert::assertEquals($actual->getExtensions(), $expected->getExtensions());
    }
}