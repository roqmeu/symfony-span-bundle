<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

class RabbitMqConnectionMetadata
{
    public string $connectionName;

    public ?string $host;

    public ?int $port;

    public function __construct(string $connectionName, ?string $host = null, ?int $port = null)
    {
        $this->connectionName = $connectionName;

        $this->host = $host ?: null;
        $this->port = $port ?: null;
    }
}
