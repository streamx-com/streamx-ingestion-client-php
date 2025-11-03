<?php declare(strict_types=1);

namespace Streamx\Clients\Ingestion\Tests\Testing\Model;

class Page
{
    public string $content;

    public function __construct(string $content)
    {
        $this->content = base64_encode($content);
    }
}