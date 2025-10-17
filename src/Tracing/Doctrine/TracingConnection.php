<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Tracing\SpanTracingTrait;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;

class TracingConnection implements Connection
{
    use SpanTracingTrait;
    use DbalTracingTrait;

    private Connection $connection;

    public function __construct(
        Dispatcher $dispatcher,
        TransactionPool $tracePool,
        Connection $connection,
        array $connectionParams,
        string $driverType
    ) {
        $this->dispatcher = $dispatcher;
        $this->tracePool = $tracePool;

        $this->connection = $connection;

        $this->connectionParams = $connectionParams;
        $this->driverType = $driverType;
        $this->databaseName = $connectionParams['dbname'] ?? $connectionParams['path'] ?? 'unknown';
    }

    /**
     * @return mixed
     */
    public function getNativeConnection()
    {
        return $this->connection->getNativeConnection();
    }

    public function quote($value, $type = ParameterType::STRING)
    {
        return $this->connection->quote($value, $type);
    }

    public function lastInsertId($name = null)
    {
        return $this->connection->lastInsertId($name);
    }

    public function prepare(string $sql): Statement
    {
        $statement = $this->connection->prepare($sql);

        return new TracingStatement(
            $this->dispatcher,
            $this->tracePool,
            $statement,
            $sql,
            $this->connectionParams,
            $this->driverType
        );
    }

    /**
     * @throws \Throwable
     */
    public function beginTransaction(): bool
    {
        $parent = $this->tracePool->getCurrentSpan();

        if ($parent === null) {
            return $this->connection->beginTransaction();
        }

        $spanName = 'BEGIN TRANSACTION';
        $spanSubtype = $this->getSpanSubtype();

        $span = $this->beginSpan(
            $parent,
            $spanName,
            SpanBundle::SPAN_TYPE_DB,
            $spanSubtype
        );

        $this->fillSpanContext($span, 'BEGIN TRANSACTION');

        try {
            return $this->connection->beginTransaction();
        } catch (\Throwable $error) {
            $this->errorSpan($span, $error);

            throw $error;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * @throws \Throwable
     */
    public function commit(): bool
    {
        $parent = $this->tracePool->getCurrentSpan();

        if ($parent === null) {
            return $this->connection->commit();
        }

        $spanName = 'COMMIT';
        $spanSubtype = $this->getSpanSubtype();

        $span = $this->beginSpan(
            $parent,
            $spanName,
            SpanBundle::SPAN_TYPE_DB,
            $spanSubtype
        );

        $this->fillSpanContext($span, 'COMMIT');

        try {
            return $this->connection->commit();
        } catch (\Throwable $error) {
            $this->errorSpan($span, $error);

            throw $error;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * @throws \Throwable
     */
    public function rollBack(): bool
    {
        $parent = $this->tracePool->getCurrentSpan();

        if ($parent === null) {
            return $this->connection->rollBack();
        }

        $spanName = 'ROLLBACK';
        $spanSubtype = $this->getSpanSubtype();

        $span = $this->beginSpan(
            $parent,
            $spanName,
            SpanBundle::SPAN_TYPE_DB,
            $spanSubtype
        );

        $this->fillSpanContext($span, 'ROLLBACK');

        try {
            return $this->connection->rollBack();
        } catch (\Throwable $error) {
            $this->errorSpan($span, $error);

            throw $error;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * @throws \Throwable
     */
    public function query(string $sql): Result
    {
        $parent = $this->tracePool->getCurrentSpan();

        if ($parent === null) {
            return $this->connection->query($sql);
        }

        $spanName = $this->buildSpanName($sql);
        $spanSubtype = $this->getSpanSubtype();

        $span = $this->beginSpan(
            $parent,
            $spanName,
            SpanBundle::SPAN_TYPE_DB,
            $spanSubtype
        );

        $this->fillSpanContext($span, $sql);

        try {
            return $this->connection->query($sql);
        } catch (\Throwable $e) {
            $this->errorSpan($span, $e);

            throw $e;
        } finally {
            $this->endSpan($span);
        }
    }

    /**
     * @throws \Throwable
     */
    public function exec(string $sql): int
    {
        $parent = $this->tracePool->getCurrentSpan();

        if ($parent === null) {
            return $this->connection->exec($sql);
        }

        $spanName = $this->buildSpanName($sql);
        $spanSubtype = $this->getSpanSubtype();

        $span = $this->beginSpan(
            $parent,
            $spanName,
            SpanBundle::SPAN_TYPE_DB,
            $spanSubtype
        );

        $this->fillSpanContext($span, $sql);

        try {
            return $this->connection->exec($sql);
        } catch (\Throwable $error) {
            $this->errorSpan($span, $error);

            throw $error;
        } finally {
            $this->endSpan($span);
        }
    }
}
