<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Impl;

use Exception;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;
use Streamx\Clients\Ingestion\Exceptions\StreamxClientException;
use Streamx\Clients\Ingestion\Publisher\HttpRequester;
use Streamx\Clients\Ingestion\StreamxClient;
use Streamx\Clients\Ingestion\StreamxClientBuilder;

class RestStreamxClientBuilder implements StreamxClientBuilder
{
    private string $serverUrl;
    private ?string $ingestionEndpointPath = null;
    private ?string $authToken = null;
    private ?HttpRequester $httpRequester = null;
    private ?ClientInterface $httpClient = null;

    public function __construct(string $serverUrl)
    {
        $this->serverUrl = $serverUrl;
    }

    public function setIngestionEndpointPath(string $ingestionEndpointPath): StreamxClientBuilder
    {
        $this->ingestionEndpointPath = $ingestionEndpointPath;
        return $this;
    }

    public function setAuthToken(string $authToken): StreamxClientBuilder
    {
        $this->authToken = $authToken;
        return $this;
    }

    public function setHttpRequester(HttpRequester $httpRequester): StreamxClientBuilder
    {
        $this->httpRequester = $httpRequester;
        return $this;
    }

    public function setHttpClient(ClientInterface $httpClient): StreamxClientBuilder
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function build(): StreamxClient
    {
        $this->ensureIngestionEndpointUri();
        $this->ensureHttpRequester();
        $ingestionEndpointUri = self::buildAbsoluteUri($this->serverUrl, $this->ingestionEndpointPath);
        return new RestStreamxClient($ingestionEndpointUri, $this->authToken, $this->httpRequester);
    }

    private function ensureIngestionEndpointUri(): void
    {
        if ($this->ingestionEndpointPath == null) {
            $this->ingestionEndpointPath = StreamxClient::INGESTION_ENDPOINT_PATH;
        }
    }

    private function ensureHttpRequester(): void
    {
        if ($this->httpRequester == null) {
            if ($this->httpClient == null) {
                $this->httpRequester = new GuzzleHttpRequester();
            } else {
                $this->httpRequester = new GuzzleHttpRequester($this->httpClient);
            }
        }
    }

    private static function buildAbsoluteUri(string $serverUrl, string $ingestionEndpointPath): UriInterface
    {
        $uriString = $serverUrl . $ingestionEndpointPath;
        try {
            $uri = new Uri($uriString);
        } catch (Exception $ex) {
            throw new StreamxClientException("Ingestion endpoint URI: $uriString is malformed. " . $ex->getMessage(), $ex);
        }

        if (empty($uri->getScheme())) {
            throw new StreamxClientException("Ingestion endpoint URI: $uriString is malformed. Relative URI is not supported.");
        }
        return $uri;
    }
}