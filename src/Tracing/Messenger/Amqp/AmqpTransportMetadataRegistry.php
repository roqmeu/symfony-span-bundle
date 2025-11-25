<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger\Amqp;

/**
 * Registry for AMQP Messenger transport metadata.
 *
 * Stores mapping: transport_name (alias) → AmqpTransportMetadata.
 */
class AmqpTransportMetadataRegistry
{
    /**
     * @var array<string, AmqpTransportMetadata>
     */
    private array $metadata = [];

    public function register(string $transportName, AmqpTransportMetadata $metadata): void
    {
        $this->metadata[$transportName] = $metadata;
    }

    public function get(string $transportName): ?AmqpTransportMetadata
    {
        return $this->metadata[$transportName] ?? null;
    }

    public function has(string $transportName): bool
    {
        return isset($this->metadata[$transportName]);
    }
}
