<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\State\Span;

class TracingConnection implements DriverConnection
{
    use DbalTracingTrait;

    private SpanTracer $spanTracer;

    private DriverConnection $connection;

    public function __construct(SpanTracer $spanTracer, DriverConnection $connection, array $connectionParams, string $databaseType, string $databaseName)
    {
        $this->spanTracer = $spanTracer;
        $this->connection = $connection;

        $this->connectionParams = $connectionParams;
        $this->databaseType = $databaseType;
        $this->databaseName = $databaseName;
    }

    /**
     * @throws \Throwable
     */
    public function beginTransaction(): bool
    {
        if (!$this->spanTracer->hasActiveTrace()) {
            return $this->connection->beginTransaction();
        }

        $sql = 'BEGIN TRANSACTION';

        $span = new Span($sql, SpanBundle::SPAN_TYPE_DB, $this->databaseType);

        $this->fillSpanContext($span, $sql);

        $this->spanTracer->startSpan($span);

        try {
            return $this->connection->beginTransaction();
        } catch (\Throwable $error) {
            $span->setError($error);

            throw $error;
        } finally {
            $this->spanTracer->endSpan($span);
        }
    }

    /**
     * @throws \Throwable
     */
    public function commit(): bool
    {
        if (!$this->spanTracer->hasActiveTrace()) {
            return $this->connection->commit();
        }

        $sql = 'COMMIT';

        $span = new Span($sql, SpanBundle::SPAN_TYPE_DB, $this->databaseType);

        $this->fillSpanContext($span, $sql);

        $this->spanTracer->startSpan($span);

        try {
            return $this->connection->commit();
        } catch (\Throwable $error) {
            $span->setError($error);

            throw $error;
        } finally {
            $this->spanTracer->endSpan($span);
        }
    }

    /**
     * @throws \Throwable
     */
    public function exec(string $sql): int
    {
        if (!$this->spanTracer->hasActiveTrace()) {
            return $this->connection->exec($sql);
        }

        $span = new Span($this->buildSpanName($sql), SpanBundle::SPAN_TYPE_DB, $this->databaseType);

        $this->fillSpanContext($span, $sql);

        $this->spanTracer->startSpan($span);

        try {
            return $this->connection->exec($sql);
        } catch (\Throwable $error) {
            $span->setError($error);

            throw $error;
        } finally {
            $this->spanTracer->endSpan($span);
        }
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

        return new TracingStatement($this->spanTracer, $statement, $sql, $this->connectionParams, $this->databaseType, $this->databaseName);
    }

    /**
     * @throws \Throwable
     */
    public function query(string $sql): Result
    {
        if (!$this->spanTracer->hasActiveTrace()) {
            return $this->connection->query($sql);
        }

        $span = new Span($this->buildSpanName($sql), SpanBundle::SPAN_TYPE_DB, $this->databaseType);

        $this->fillSpanContext($span, $sql);

        $this->spanTracer->startSpan($span);

        try {
            return $this->connection->query($sql);
        } catch (\Throwable $error) {
            $span->setError($error);

            throw $error;
        } finally {
            $this->spanTracer->endSpan($span);
        }
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
        if (!$this->spanTracer->hasActiveTrace()) {
            return $this->connection->rollBack();
        }

        $sql = 'ROLLBACK';

        $span = new Span($sql, SpanBundle::SPAN_TYPE_DB, $this->databaseType);

        $this->fillSpanContext($span, $sql);

        $this->spanTracer->startSpan($span);

        try {
            return $this->connection->rollBack();
        } catch (\Throwable $error) {
            $span->setError($error);

            throw $error;
        } finally {
            $this->spanTracer->endSpan($span);
        }
    }
}
