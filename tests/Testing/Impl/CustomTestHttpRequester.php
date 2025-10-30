<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Tests\Testing\Impl;

use Psr\Http\Message\UriInterface;
use Streamx\Clients\Ingestion\Impl\GuzzleHttpRequester;
use Streamx\Clients\Ingestion\Publisher\HttpRequester;
use Streamx\Clients\Ingestion\StreamxClient;

class CustomTestHttpRequester implements HttpRequester
{

    private string $ingestionEndpointPath;
    private HttpRequester $httpRequester;

    public function __construct(string $ingestionEndpointPath)
    {
        $this->ingestionEndpointPath = $ingestionEndpointPath;
        $this->httpRequester = new GuzzleHttpRequester();
    }

    public function post(UriInterface $endpointUri, array $headers, array $cloudEvents, array $additionalRequestOptions = []): array
    {
        $endpointUri = $this->modifyUri($endpointUri);
        return $this->httpRequester->post($endpointUri, $headers, $cloudEvents, $additionalRequestOptions);
    }

    private function modifyUri(UriInterface $uri): UriInterface
    {
        return $uri->withPath(
            str_replace(StreamxClient::INGESTION_ENDPOINT_PATH,
                $this->ingestionEndpointPath, $uri->getPath())
        );
    }
}