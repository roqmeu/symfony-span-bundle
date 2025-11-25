<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger\Amqp;

/**
 * Metadata for AMQP Messenger transport.
 *
 * Contains exchange details extracted during transport creation.
 */
class AmqpTransportMetadata
{
    public string $transportName;

    public ?string $host;

    public ?int $port;

    public ?string $exchangeName;

    public function __construct(string $transportName, ?string $host, ?int $port, ?string $exchangeName = null)
    {
        $this->transportName = $transportName;

        $this->host = $host ?: null;
        $this->port = $port ?: null;
        $this->exchangeName = $exchangeName ?: null;
    }
}
