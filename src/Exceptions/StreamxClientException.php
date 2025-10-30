<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Exceptions;

use CloudEvents\V1\CloudEventInterface;
use Exception;

/**
 * Thrown when client failed command handling.
 */
class StreamxClientException extends Exception
{

    /**
     * @var CloudEventInterface[]
     */
    private array $responseEvents;

    /**
     * @param CloudEventInterface[] $responseEvents
     */
    public function __construct(string $message, Exception $previous = null, array $responseEvents = []) {
        parent::__construct($message, 0, $previous);
        $this->responseEvents = $responseEvents;
    }

    /**
     * @return CloudEventInterface[]
     */
    public function getResponseEvents(): array {
        return $this->responseEvents;
    }
}