<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Publisher;

use CloudEvents\V1\CloudEventInterface;
use Psr\Http\Message\UriInterface;
use Streamx\Clients\Ingestion\Exceptions\StreamxClientException;

/**
 * An interface that allows to inject custom HTTP client implementation.
 */
interface HttpRequester
{
    /**
     * Executes a StreamX Ingestion POST request.
     *
     * @param UriInterface $endpointUri Request target URI.
     * @param array $headers Request headers.
     * @param CloudEventInterface[] $cloudEvents Array of CloudEvents.
     * @param array $additionalRequestOptions Additional request options. Optional.
     *   With default implementation, the supported options are: https://docs.guzzlephp.org/en/stable/request-options.html
     * @return CloudEventInterface[] representing successful ingestion result.
     * @throws StreamxClientException if request failed.
     */
    public function post(
        UriInterface $endpointUri,
        array $headers,
        array $cloudEvents,
        array $additionalRequestOptions = []
    ): array;
}