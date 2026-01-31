<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

class RabbitMqConnectionMetadataRegistry
{
    /**
     * @var array<string, RabbitMqConnectionMetadata>
     */
    private array $metadata = [];

    public function register(string $connectionName, RabbitMqConnectionMetadata $metadata): void
    {
        $this->metadata[$connectionName] = $metadata;
    }

    public function get(string $connectionName): ?RabbitMqConnectionMetadata
    {
        return $this->metadata[$connectionName] ?? null;
    }

    public function has(string $connectionName): bool
    {
        return isset($this->metadata[$connectionName]);
    }
}
