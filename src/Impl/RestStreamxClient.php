<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Impl;

use Psr\Http\Message\UriInterface;
use Streamx\Clients\Ingestion\Publisher\HttpRequester;
use Streamx\Clients\Ingestion\Publisher\Publisher;
use Streamx\Clients\Ingestion\StreamxClient;

class RestStreamxClient implements StreamxClient
{
    private RestPublisherProvider $publisherProvider;

    public function __construct(
        UriInterface $ingestionEndpointUri,
        ?string $authToken,
        HttpRequester $httpRequester
    ) {
        $this->publisherProvider = new RestPublisherProvider($ingestionEndpointUri, $authToken, $httpRequester);
    }

    public function newPublisher(): Publisher
    {
        return $this->publisherProvider->newPublisher();
    }
}