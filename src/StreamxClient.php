<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion;

use Streamx\Clients\Ingestion\Publisher\Publisher;

/**
 * Represents a client that can communicate with StreamX Ingestion Service.
 */
interface StreamxClient
{
    /**
     * StreamX REST Ingestion path.
     */
    public const INGESTION_ENDPOINT_PATH = '/ingestion/v2/cloudevents';

    /**
     * Creates new {@link Publisher} instance.
     * @return Publisher
     */
    public function newPublisher(): Publisher;

}