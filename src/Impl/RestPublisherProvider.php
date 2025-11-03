<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Impl;

use Psr\Http\Message\UriInterface;
use Streamx\Clients\Ingestion\Publisher\HttpRequester;
use Streamx\Clients\Ingestion\Publisher\Publisher;

class RestPublisherProvider
{
    private UriInterface $ingestionEndpointUri;
    private ?string $authToken;
    private HttpRequester $httpRequester;

    public function __construct(
        UriInterface $ingestionEndpointUri,
        ?string $authToken,
        HttpRequester $httpRequester
    ) {
        $this->ingestionEndpointUri = $ingestionEndpointUri;
        $this->authToken = $authToken;
        $this->httpRequester = $httpRequester;
    }

    public function newPublisher(): Publisher
    {
        return new RestPublisher(
            $this->ingestionEndpointUri,
            $this->authToken,
            $this->httpRequester
        );
    }
}