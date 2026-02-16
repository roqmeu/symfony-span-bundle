<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span;

abstract class AbstractTracingDbal
{
    /**
     * @var array<string, mixed>
     */
    protected array $connectionParams = [];

    protected string $databaseType = SpanBundle::SPAN_SUBTYPE_DOCTRINE;

    protected string $databaseName = SpanBundle::UNKNOWN;

    /**
     * @param array<string, mixed> $connectionParams
     */
    protected function initDbalTracingContext(array $connectionParams, string $databaseType, string $databaseName): void
    {
        $this->connectionParams = $connectionParams;
        $this->databaseType = $databaseType;
        $this->databaseName = $databaseName;
    }

    protected function fillSpanContext(Span $span, string $sql): void
    {
        $host = ($this->connectionParams['host'] ?? '') ?: null;

        $port = $this->connectionParams['port'] ?? 0;
        $port = $port > 0 ? $port : null;

        $span->context->server = [
            'host' => $host,
            'port' => $port,
        ];

        $span->context->db = [
            'instance' => $this->databaseName,
            'name' => $this->databaseName,
            'statement' => $sql,
            'system' => $this->databaseType,
            'type' => 'sql',
        ];
    }

    protected function determineDatabaseTypeFromPlatform(AbstractPlatform $platform): string
    {
        return self::mapPlatformToDatabaseType($platform);
    }

    public static function mapPlatformToDatabaseType(AbstractPlatform $platform): string
    {
        $platformClassName = \strtolower(\get_class($platform));

        if (\str_contains($platformClassName, 'postgresql')) {
            return SpanBundle::SPAN_SUBTYPE_POSTGRESQL;
        }

        if (\str_contains($platformClassName, 'mysql') || \str_contains($platformClassName, 'mariadb')) {
            return SpanBundle::SPAN_SUBTYPE_MYSQL;
        }

        if (\str_contains($platformClassName, 'sqlite')) {
            return SpanBundle::SPAN_SUBTYPE_SQLITE;
        }

        if (\str_contains($platformClassName, 'oracle')) {
            return SpanBundle::SPAN_SUBTYPE_ORACLE;
        }

        if (\str_contains($platformClassName, 'sqlserver')) {
            return SpanBundle::SPAN_SUBTYPE_MSSQL;
        }

        return SpanBundle::SPAN_SUBTYPE_DOCTRINE;
    }

    /**
     * @param array<string, mixed> $connectionParams
     */
    protected function determineDatabaseName(array $connectionParams): string
    {
        $databaseName = $connectionParams['dbname'] ?? $connectionParams['path'] ?? SpanBundle::UNKNOWN;

        return \is_string($databaseName) && $databaseName !== '' ? $databaseName : SpanBundle::UNKNOWN;
    }
}
