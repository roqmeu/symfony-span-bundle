<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

class TracingConnectionV4 extends AbstractTracingConnection implements DriverConnection
{
    public function prepare(string $sql): Statement
    {
        $statement = $this->connection->prepare($sql);

        return new TracingStatementV4($this->spanTracer, $statement, $sql, $this->connectionParams, $this->databaseType, $this->databaseName);
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

    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }

    /**
     * @throws \Throwable
     *
     * @return int|numeric-string
     */
    public function exec(string $sql): int|string
    {
        /** @var int|string $result */
        $result = $this->traceSql($sql, function () use ($sql): int|string {
            return $this->connection->exec($sql);
        });

        if (\is_string($result)) {
            /** @var numeric-string $result */
            $result = $result;
        }

        return $result;
    }

    /**
     * @return int|numeric-string
     */
    public function lastInsertId(): int|string
    {
        $result = $this->connection->lastInsertId();

        if (\is_string($result)) {
            /** @var numeric-string $result */
            $result = $result;
        }

        return $result;
    }

    /**
     * @throws \Throwable
     */
    public function beginTransaction(): void
    {
        $this->traceSql('BEGIN TRANSACTION', function (): void {
            $this->connection->beginTransaction();
        }, 'BEGIN TRANSACTION');
    }

    /**
     * @throws \Throwable
     */
    public function commit(): void
    {
        $this->traceSql('COMMIT', function (): void {
            $this->connection->commit();
        }, 'COMMIT');
    }

    /**
     * @throws \Throwable
     */
    public function rollBack(): void
    {
        $this->traceSql('ROLLBACK', function (): void {
            $this->connection->rollBack();
        }, 'ROLLBACK');
    }

    /**
     * @return resource|object
     */
    public function getNativeConnection()
    {
        return $this->connection->getNativeConnection();
    }

    public function getServerVersion(): string
    {
        return $this->connection->getServerVersion();
    }
}
