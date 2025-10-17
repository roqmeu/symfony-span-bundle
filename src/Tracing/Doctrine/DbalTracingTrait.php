<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span;

trait DbalTracingTrait
{
    private array $connectionParams;
    private string $driverType;
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

        if ($result === 1) {
            return $this->cleanTableName($matches[1]);
        }

        return null;
    }

    private function cleanTableName(string $tableName): string
    {
        $tableName = \trim($tableName, '"`\'');

        // TODO replace to substr
        if (\strpos($tableName, '.') !== false) {
            $parts = \explode('.', $tableName);

            return $parts[\array_key_last($parts)];
        }

        return $tableName;
    }

    private function getSpanSubtype(): string
    {
        switch ($this->driverType) {
            case 'postgresql':
                return SpanBundle::SPAN_SUBTYPE_POSTGRESQL;
            case 'mysql':
                return SpanBundle::SPAN_SUBTYPE_MYSQL;
            default:
                return SpanBundle::SPAN_SUBTYPE_DOCTRINE;
        }
    }

    private function fillSpanContext(Span $span, string $sql): void
    {
        $span->context->db = [
            'system' => $this->driverType,
            'name' => $this->databaseName,
        ];

        if (isset($this->connectionParams['host']) && $this->connectionParams['host'] !== '') {
            $span->context->server = [
                'address' => $this->connectionParams['host'],
            ];

            if (isset($this->connectionParams['port'])) {
                $span->context->server['port'] = (int)$this->connectionParams['port'];
            }
        }

        $span->context->target = [
            'type' => $this->driverType,
            'name' => $this->databaseName,
        ];

        $span->context->db_statement = $sql;
        $span->context->db_type = 'sql';
        $span->context->db_instance = $this->databaseName;
    }
}
