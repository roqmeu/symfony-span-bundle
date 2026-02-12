<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

class TracingConnectionV3 extends AbstractTracingConnection implements DriverConnection
{
    /**
     * @throws \Throwable
     */
    public function beginTransaction(): bool
    {
        return (bool) $this->traceSql('BEGIN TRANSACTION', function (): bool {
            return $this->connection->beginTransaction();
        }, 'BEGIN TRANSACTION');
    }

    /**
     * @throws \Throwable
     */
    public function commit(): bool
    {
        return (bool) $this->traceSql('COMMIT', function (): bool {
            return $this->connection->commit();
        }, 'COMMIT');
    }

    /**
     * @throws \Throwable
     */
    public function exec(string $sql): int
    {
        return (int) $this->traceSql($sql, function () use ($sql): int {
            return $this->connection->exec($sql);
        });
    }

    /**
     * @return mixed
     */
    public function getNativeConnection()
    {
        return $this->connection->getNativeConnection();
    }

    public function lastInsertId($name = null)
    {
        return $this->connection->lastInsertId($name);
    }

    public function prepare(string $sql): Statement
    {
        $statement = $this->connection->prepare($sql);

        return new TracingStatementV3($this->spanTracer, $statement, $sql, $this->connectionParams, $this->databaseType, $this->databaseName);
    }

    /**
     * @throws \Throwable
     */
    public function query(string $sql): Result
    {
        /** @var Result $result */
        $result = $this->traceSql($sql, function () use ($sql): Result {
            return $this->connection->query($sql);
        });

        return $result;
    }

    public function quote($value, $type = ParameterType::STRING)
    {
        return $this->connection->quote($value, $type);
    }

    /**
     * @throws \Throwable
     */
    public function rollBack(): bool
    {
        return (bool) $this->traceSql('ROLLBACK', function (): bool {
            return $this->connection->rollBack();
        }, 'ROLLBACK');
    }
}
