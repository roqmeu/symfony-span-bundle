<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\State\Span;

abstract class AbstractTracingConnection extends AbstractTracingDbal
{
    protected SpanTracer $spanTracer;

    protected DriverConnection $connection;

    /**
     * @param array<string, mixed> $connectionParams
     */
    public function __construct(SpanTracer $spanTracer, DriverConnection $connection, array $connectionParams, string $databaseType, string $databaseName)
    {
        $this->spanTracer = $spanTracer;
        $this->connection = $connection;

        $this->initDbalTracingContext($connectionParams, $databaseType, $databaseName);
    }

    /**
     * @param callable(): mixed $callback
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    protected function traceSql(string $sql, callable $callback, ?string $spanName = null)
    {
        if (!$this->spanTracer->hasActiveTrace()) {
            return $callback();
        }

        $spanTitle = $spanName ?? $this->buildSpanName($sql);
        $span = new Span($spanTitle, SpanBundle::SPAN_TYPE_DB, $this->databaseType);

        $this->fillSpanContext($span, $sql);

        $this->spanTracer->startSpan($span);

        try {
            return $callback();
        } catch (\Throwable $error) {
            $span->setError($error);

            throw $error;
        } finally {
            $this->spanTracer->endSpan($span);
        }
    }
}
