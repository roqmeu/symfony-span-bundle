<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span;

trait DbalTracingTrait
{
    private array $connectionParams;

    private string $databaseType;

    private string $databaseName;

    private function buildSpanName(string $sql): string
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

        if ($result === 1 && \count($matches) !== 0) {
            return $this->cleanTableName($matches[1]);
        }

        return null;
    }

    private function fillSpanContext(Span $span, string $sql): void
    {
        if (isset($this->connectionParams['host']) && $this->connectionParams['host'] !== '') {
            $span->context->server = [
                'host' => $this->connectionParams['host'],
            ];

            if (isset($this->connectionParams['port']) && $this->connectionParams['port'] !== '') {
                $span->context->server['port'] = (int)$this->connectionParams['port'];
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

    private function determineDatabaseType(Driver $driver): string
    {
        try {
            $platform = $driver->getDatabasePlatform();

            // TODO getName deprecated in DBAL 3.x - Identify platforms by their class.
            switch ($platform->getName()) {
                case 'mssql':
                    return SpanBundle::SPAN_SUBTYPE_MSSQL;
                case 'mariadb':
                case 'mysql':
                    return SpanBundle::SPAN_SUBTYPE_MYSQL;
                case 'oracle':
                    return SpanBundle::SPAN_SUBTYPE_ORACLE;
                case 'postgresql':
                    return SpanBundle::SPAN_SUBTYPE_POSTGRESQL;
                case 'sqlite':
                    return SpanBundle::SPAN_SUBTYPE_SQLITE;
                default:
                    return SpanBundle::SPAN_SUBTYPE_DOCTRINE;
            }
        } catch (\Throwable $e) {
            return SpanBundle::SPAN_SUBTYPE_DOCTRINE;
        }
    }

    private function determineDatabaseName(array $connectionParams): string
    {
        return $connectionParams['dbname'] ?? $connectionParams['path'] ?? SpanBundle::UNKNOWN;
    }

    private function determineHost(array $connectionParams): ?string
    {
        return ($connectionParams['host'] ?? null) ?: null;
    }

    private function determinePort(array $connectionParams): ?int
    {
        return ((int)($connectionParams['port'] ?? null)) ?: null;
    }
}
