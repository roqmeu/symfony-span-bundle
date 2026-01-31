<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger\Doctrine;

/**
 * Metadata for Doctrine Messenger transport.
 *
 * Contains database connection details extracted during transport creation.
 */
class DoctrineTransportMetadata
{
    public string $transportName;

    public ?string $host;

    public ?int $port;

    public ?string $databaseType;

    public ?string $databaseName;

    public ?string $tableName;

    public ?string $queueName;

    public function __construct(
        string $transportName,
        ?string $host,
        ?int $port,
        ?string $databaseType = null,
        ?string $databaseName = null,
        ?string $tableName = null,
        ?string $queueName = null
    ) {
        $this->transportName = $transportName;

        $this->host = $host ?: null;
        $this->port = $port ?: null;
        $this->databaseType = $databaseType ?: null;
        $this->databaseName = $databaseName ?: null;
        $this->tableName = $tableName ?: null;
        $this->queueName = $queueName ?: null;
    }
}
