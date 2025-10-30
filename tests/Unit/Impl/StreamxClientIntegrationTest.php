<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Tests\Unit\Impl;

use DateTimeImmutable;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Streamx\Clients\Ingestion\Builders\StreamxClientBuilders;
use Streamx\Clients\Ingestion\Publisher\Publisher;
use Streamx\Clients\Ingestion\StreamxClient;
use Streamx\Clients\Ingestion\Tests\Testing\Model\CloudEventUtils;
use Streamx\Clients\Ingestion\Tests\Testing\Model\Page;

/**
 * This Integration Test requires a running StreamX instance.
 * You can use tests/resources/mesh.yaml as minimal mesh setup.
 */
class StreamxClientIntegrationTest extends TestCase {

    private const INGESTION_BASE_URL = "http://localhost:8080";
    private const DELIVERY_BASE_URL = "http://localhost:8081";
    private const TIMEOUT_SECONDS = 3;

    private static StreamxClient $client;
    private static Publisher $publisher;

    public static function setUpBeforeClass(): void {
        if (!self::isStreamxAvailable()) {
            self::markTestSkipped('Skipping test because StreamX is not available');
        }

        self::$client = StreamxClientBuilders::create(self::INGESTION_BASE_URL)->build();
        self::$publisher = self::$client->newPublisher();
    }

    private static function isStreamxAvailable(): bool {
        $ch = curl_init(self::INGESTION_BASE_URL . '/q/health');

        curl_setopt($ch, CURLOPT_NOBODY, true);         // Don't download body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Prevent output
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);           // Timeout in seconds
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
    }

    /** @test */
    public function shouldPublishAndUnpublishPageAsObject() {
        $key = 'page-object-key';
        $content = 'Test content from php client';
        $page = new Page($content);

        $pageObjectPublishEvent = CloudEventUtils::pagePublishEvent($key, $page, 10);
        self::$publisher->send($pageObjectPublishEvent);
        $this->assertPageIsPublished($pageObjectPublishEvent->getSubject(), $content);

        $pageObjectUnpublishEvent = CloudEventUtils::pageUnpublishEvent($key, 11);
        self::$publisher->send($pageObjectUnpublishEvent);
        $this->assertPageIsUnpublished($pageObjectPublishEvent->getSubject());
    }

    /** @test */
    public function shouldPublishAndUnpublishPageAsArray() {
        $key = 'page-array-key';
        $content = 'Test content from php client';
        $page = ['content' => base64_encode($content)];

        $pageArrayPublishEvent = CloudEventUtils::pageArrayPublishEvent($key, $page, 20);
        self::$publisher->send($pageArrayPublishEvent);
        $this->assertPageIsPublished($pageArrayPublishEvent->getSubject(), $content);

        $pageArrayUnpublishEvent = CloudEventUtils::pageUnpublishEvent($key, 21);
        self::$publisher->send($pageArrayUnpublishEvent);
        $this->assertPageIsUnpublished($pageArrayPublishEvent->getSubject());
    }

    /** @test */
    public function shouldPublishAndUnpublishMultiEventRequest() {
        $keyPrefix = "multievent-page-object-key";
        $keys = [];
        for ($i = 0; $i < 10; $i++) {
            $keys[] = "$keyPrefix-$i";
        }

        $this->verifyMultiEventPublish($keys);
        $this->verifyMultiEventUnpublish($keys);
    }

    private function verifyMultiEventPublish(array $keys) {
        // given
        $inputEvents = [];
        foreach ($keys as $key) {
            $page = new Page("Page with key $key");
            $inputEvents[] = CloudEventUtils::pagePublishEvent($key, $page, 1);
        }

        // when
        $resultEvents = self::$publisher->sendMulti($inputEvents);

        // then
        $this->verifyStreamxResponse($inputEvents, $resultEvents);

        // and
        foreach ($keys as $key) {
            $this->assertPageIsPublished($key, "Page with key $key");
        }
    }

    private function verifyMultiEventUnpublish(array $keys) {
        // given
        $inputEvents = [];
        foreach ($keys as $key) {
            $inputEvents[] = CloudEventUtils::pageUnpublishEvent($key, 1);
        }

        // when
        $resultEvents = self::$publisher->sendMulti($inputEvents);

        // then
        $this->verifyStreamxResponse($inputEvents, $resultEvents);

        // and
        foreach ($keys as $key) {
            $this->assertPageIsUnpublished($key);
        }
    }

    private function verifyStreamxResponse(array $inputEvents, array $resultEvents): void {
        $this->assertSameSize($inputEvents, $resultEvents);
        for ($i = 0; $i < count($inputEvents); $i++) {
            $inputEvent = $inputEvents[$i];
            $resultEvent = $resultEvents[$i];

            Assert::assertNotEquals($inputEvent->getId(), $resultEvent->getId());
            Assert::assertEquals('rest-ingestion', $resultEvent->getSource());
            Assert::assertEquals('com.streamx.event.processing.success', $resultEvent->getType());
            Assert::assertEquals(
<<<JSON
{
    "specversion": "1.0",
    "id": "id",
    "source": "source",
    "type": "{$inputEvent->getType()}",
    "subject": "{$inputEvent->getSubject()}",
    "time": "1970-01-01T00:00:01Z"
}
JSON, json_encode($resultEvent->getData(), JSON_PRETTY_PRINT));
            Assert::assertEquals($inputEvent->getDataContentType(), $resultEvent->getDataContentType());
            Assert::assertEquals($inputEvent->getDataSchema(), $resultEvent->getDataSchema());
            Assert::assertEquals($inputEvent->getSubject(), $resultEvent->getSubject());
            Assert::assertEquals((new DateTimeImmutable())->format('Y-m-d H:i:s'), $resultEvent->getTime()->format('Y-m-d H:i:s'));
            Assert::assertEquals($inputEvent->getExtensions(), $resultEvent->getExtensions());
        }
    }

    private function assertPageIsPublished(string $key, string $content) {
        $url = self::DELIVERY_BASE_URL . '/' . $key;
    
        $startTime = time();
        while (time() - $startTime < self::TIMEOUT_SECONDS) {
            $response = @file_get_contents($url);
            if ($response !== false) {
                $this->assertEquals($response, $content);
                return;
            }
            usleep(100000); // sleep for 100 milliseconds
        }
        
        $this->fail("$url: page not found");
    }

    private function assertPageIsUnpublished(string $key) {
        $url = self::DELIVERY_BASE_URL . '/' . $key;
    
        $startTime = time();
        while (time() - $startTime < self::TIMEOUT_SECONDS) {
            $response = @file_get_contents($url);
            if (empty($response)) {
                $this->assertTrue(true);
                return;
            }
            usleep(100000); // sleep for 100 milliseconds
        }
        
        $this->fail("$url: page exists");
    }
}

