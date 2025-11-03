<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion;

use Psr\Http\Client\ClientInterface;
use Streamx\Clients\Ingestion\Exceptions\StreamxClientException;
use Streamx\Clients\Ingestion\Publisher\HttpRequester;

/**
 * Builder for customized {@link StreamxClient} instances.
 */
interface StreamxClientBuilder
{

    /**
     * Configures custom StreamX REST Ingestion endpoint path. Default value is
     * {@link StreamxClient::INGESTION_ENDPOINT_PATH}.
     * @param string $ingestionEndpointPath StreamX REST Ingestion endpoint path.
     * @return StreamxClientBuilder
     */
    public function setIngestionEndpointPath(string $ingestionEndpointPath): StreamxClientBuilder;

    /**
     * Configures authentication token.
     * @param string $authToken Auth token used for authentication on the server side.
     * @return StreamxClientBuilder
     */
    public function setAuthToken(string $authToken): StreamxClientBuilder;

    /**
     * Configures custom {@link HttpRequester}. If set then custom {@link Client} set by
     * {@link StreamxClientBuilder::setHttpClient} is ignored.
     * @param HttpRequester $httpRequester Custom {@link HttpRequester}.
     * @return StreamxClientBuilder
     */
    public function setHttpRequester(HttpRequester $httpRequester): StreamxClientBuilder;

    /**
     * Configures custom {@link ClientInterface} for default {@link HttpRequester}. It is ignored when
     * custom {@link HttpRequester} is set using {@link StreamxClientBuilder::setHttpRequester()}.
     * @param ClientInterface $httpClient Custom {@link Client} instance.
     * @return StreamxClientBuilder
     */
    public function setHttpClient(ClientInterface $httpClient): StreamxClientBuilder;

    /**
     * Builds actual {@link StreamxClient} instance.
     * @return StreamxClient
     * @throws StreamxClientException if {@link StreamxClient} instance could not be created.
     */
    public function build(): StreamxClient;
}