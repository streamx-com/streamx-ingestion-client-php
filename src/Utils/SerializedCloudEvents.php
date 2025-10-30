<?php

namespace Streamx\Clients\Ingestion\Utils;

class SerializedCloudEvents {

    private string $json;
    private string $contentType;

    function __construct(string $json, string $contentType) {
        $this->json = $json;
        $this->contentType = $contentType;
    }

    public function getJson(): string {
        return $this->json;
    }

    public function getContentType(): string {
        return $this->contentType;
    }
}