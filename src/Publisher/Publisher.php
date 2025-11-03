<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Publisher;

use CloudEvents\V1\CloudEventInterface;
use Streamx\Clients\Ingestion\Exceptions\StreamxClientException;

/**
 * StreamX ingestion endpoint contract. `Publisher` instance is reusable.
 */
abstract class Publisher
{
    /**
     * Ingestion endpoint command.
     * Sends a single CloudEvent for ingestion.
     *
     * @param CloudEventInterface $cloudEvent CloudEvent payload
     * @param array $additionalRequestOptions Additional request options. Optional.
     *    With default implementation, the supported options are: https://docs.guzzlephp.org/en/stable/request-options.html
     * @return CloudEventInterface containing ingestion success information.
     *    Ingestion failure of any kind causes {@link StreamxClientException}.
     * @throws StreamxClientException If command failed.
     */
    public abstract function send(CloudEventInterface $cloudEvent, array $additionalRequestOptions = []): CloudEventInterface;

    /**
     * Ingestion endpoint command.
     * Sends a list of CloudEvents for ingestion.
     *
     * @param CloudEventInterface[] $cloudEvents CloudEvents list payload
     * @param array $additionalRequestOptions Additional request options. Optional.
     *    With default implementation, the supported options are: https://docs.guzzlephp.org/en/stable/request-options.html
     * @return CloudEventInterface[] containing ingestion success information of each sent CloudEvent.
     *    Ingestion failure of any kind, of any given event, causes {@link StreamxClientException}.
     * @throws StreamxClientException If command failed.
     */
    public abstract function sendMulti(array $cloudEvents, array $additionalRequestOptions = []): array;
}