<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Impl;

use CloudEvents\V1\CloudEventInterface;
use Psr\Http\Message\UriInterface;
use Streamx\Clients\Ingestion\Publisher\HttpRequester;
use Streamx\Clients\Ingestion\Publisher\Publisher;

class RestPublisher extends Publisher
{
    private UriInterface $ingestionEndpointUri;
    private array $headers;
    private HttpRequester $httpRequester;

    public function __construct(
        UriInterface $ingestionEndpointUri,
        ?string $authToken,
        HttpRequester $httpRequester
    ) {
        $this->ingestionEndpointUri = $ingestionEndpointUri;
        $this->headers = $this->buildHttpHeaders($authToken);
        $this->httpRequester = $httpRequester;
    }

    private function buildHttpHeaders(?string $authToken): array
    {
        if (empty($authToken)) {
            return [];
        }
        return ['Authorization' => 'Bearer ' . $authToken];
    }

    public function send(CloudEventInterface $cloudEvent, array $additionalRequestOptions = []): CloudEventInterface
    {
        return $this->sendMulti([$cloudEvent], $additionalRequestOptions)[0];
    }

    public function sendMulti(array $cloudEvents, array $additionalRequestOptions = []): array
    {
        return $this->httpRequester->post(
            $this->ingestionEndpointUri,
            $this->headers,
            $cloudEvents,
            $additionalRequestOptions
        );
    }
}