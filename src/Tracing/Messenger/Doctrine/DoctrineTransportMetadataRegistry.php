<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger\Doctrine;

/**
 * Registry for Doctrine Messenger transport metadata.
 *
 * Stores mapping: transport_name (alias) → DoctrineTransportMetadata.
 */
class DoctrineTransportMetadataRegistry
{
    /**
     * @var array<string, DoctrineTransportMetadata>
     */
    private array $metadata = [];

    public function register(string $transportName, DoctrineTransportMetadata $metadata): void
    {
        $this->metadata[$transportName] = $metadata;
    }

    public function get(string $transportName): ?DoctrineTransportMetadata
    {
        return $this->metadata[$transportName] ?? null;
    }

    public function has(string $transportName): bool
    {
        return isset($this->metadata[$transportName]);
    }
}
