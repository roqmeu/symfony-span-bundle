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

    protected function buildSpanName(string $sql): string
    {
        $sql = \trim($sql);

        if (\preg_match('/^\s*(\w+)/', $sql, $matches) === 1) {
            $operation = \strtoupper($matches[1]);

            $tableName = $this->extractTableName($sql, $operation);

            if ($tableName !== null) {
                return "{$operation} {$tableName}";
            }
        }

        return "QUERY {$this->databaseName}";
    }

    protected function fillSpanContext(Span $span, string $sql): void
    {
        if (isset($this->connectionParams['host']) && $this->connectionParams['host'] !== '') {
            $span->context->server = [
                'host' => $this->connectionParams['host'],
            ];

            if (isset($this->connectionParams['port']) && $this->connectionParams['port'] !== '') {
                $span->context->server['port'] = (int) $this->connectionParams['port'];
            }
        }

        $span->context->target = [
            'type' => $this->databaseType,
            'name' => $this->databaseName,
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

    private function cleanTableName(string $tableName): string
    {
        $tableName = \trim($tableName, '"`\'');

        if (\strpos($tableName, '.') !== false) {
            $parts = \explode('.', $tableName);

            return $parts[\array_key_last($parts)];
        }

        return $tableName;
    }

    private function extractTableName(string $sql, string $operation): ?string
    {
        $matches = [];

        switch ($operation) {
            case 'SELECT':
                $result = \preg_match('/FROM\s+([^\s,;()]+)/i', $sql, $matches);
                break;
            case 'INSERT':
                $result = \preg_match('/^\s*INSERT\s+INTO\s+([^\s,;()]+)/i', $sql, $matches);
                break;
            case 'UPDATE':
                $result = \preg_match('/^\s*UPDATE\s+([^\s,;()]+)/i', $sql, $matches);
                break;
            case 'DELETE':
                $result = \preg_match('/^\s*DELETE\s+FROM\s+([^\s,;()]+)/i', $sql, $matches);
                break;
            default:
                $result = false;
        }

        if ($result === 1 && $matches !== []) {
            return $this->cleanTableName($matches[1]);
        }

        return null;
    }
}
