<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Impl;

use CloudEvents\V1\CloudEventInterface;
use Exception;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Streamx\Clients\Ingestion\Exceptions\StreamxClientException;
use Streamx\Clients\Ingestion\Publisher\HttpRequester;
use Streamx\Clients\Ingestion\Utils\CloudEventsSerializer;

class GuzzleHttpRequester implements HttpRequester
{
    private const CONTENT_TYPE_HEADER = 'Content-Type';

    private ClientInterface $httpClient;

    public function __construct(?ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new GuzzleHttpClient();
    }

    public function post(UriInterface $endpointUri, array $headers, array $cloudEvents, array $additionalRequestOptions = []): array {
        try {
            $serializedEvents =  CloudEventsSerializer::serialize($cloudEvents);
            $headers = array_merge($headers, [self::CONTENT_TYPE_HEADER => $serializedEvents->getContentType()]);

            $requestOptions = array_merge(
                [
                    RequestOptions::HEADERS => $headers,
                    RequestOptions::BODY => $serializedEvents->getJson(),
                    RequestOptions::HTTP_ERRORS => false
                ],
                $additionalRequestOptions
            );

            $response = $this->httpClient->request('POST', $endpointUri, $requestOptions);
            return $this->handleIngestionResponse($response);
        } catch (ClientExceptionInterface $e) {
            throw new StreamxClientException(
                sprintf('Ingestion POST request with URI: %s failed due to HTTP client error', $endpointUri),
                $e);
        }
    }

    /**
     * @return CloudEventInterface[]
     * @throws StreamxClientException
     */
    private function handleIngestionResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $contentType = $this->getContentType($response);
        $responseBody = $this->getResponseBody($response, $statusCode);
        $responseText = str_contains($contentType, "text/plain") ? $responseBody : '';
        $responseEvents = $this->getResponseEvents($responseBody, $contentType, $statusCode);

        switch ($statusCode) {
            case 202:
            {
                if (empty($responseEvents)) {
                    throw new StreamxClientException("Success response contains no response events");
                }
                return $responseEvents;
            }
            case 400:
                throw new StreamxClientException("Bad request. $responseText");
            case 401:
                throw new StreamxClientException('Authentication failed. Make sure that the given token is valid.');
            case 403:
                throw new StreamxClientException("Forbidden. $responseText");
            case 500:
                throw new StreamxClientException("Unexpected server error. $responseText", null, $responseEvents);
            case 503:
                throw new StreamxClientException("Service unavailable. $responseText");
            default:
                throw new StreamxClientException("Communication error. Response status: $statusCode. Message: " . $response->getReasonPhrase());
        }
    }

    private static function getContentType(ResponseInterface $response): string {
        return $response->hasHeader(self::CONTENT_TYPE_HEADER)
            ? $response->getHeader(self::CONTENT_TYPE_HEADER)[0]
            : '';
    }

    private static function getResponseBody(ResponseInterface $response, int $statusCode): string {
        try {
            return $response->getBody()->getContents();
        } catch (Exception $ex) {
            throw new StreamxClientException("Response could not be read. Response status: $statusCode", $ex);
        }
    }

    /**
     * @return CloudEventInterface[]
     */
    private static function getResponseEvents(string $responseBody, string $contentType, int $statusCode): array {
        try {
            return CloudEventsSerializer::deserialize($responseBody, $contentType);
        } catch (Exception $ex) {
            throw new StreamxClientException(
                "Error while parsing body of response with HTTP status $statusCode. " . $ex->getMessage(), $ex);
        }
    }

}