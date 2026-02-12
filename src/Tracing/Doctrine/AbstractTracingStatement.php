<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Statement;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\State\Span;

abstract class AbstractTracingStatement extends AbstractTracingDbal
{
    protected SpanTracer $spanTracer;

    protected Statement $statement;

    protected string $sql;

    /**
     * @param array<string, mixed> $connectionParams
     */
    public function __construct(
        SpanTracer $spanTracer,
        Statement $statement,
        string $sql,
        array $connectionParams,
        string $databaseType,
        string $databaseName
    ) {
        $this->spanTracer = $spanTracer;
        $this->statement = $statement;
        $this->sql = $sql;

        $this->initDbalTracingContext($connectionParams, $databaseType, $databaseName);
    }

    /**
     * @param callable(): mixed $callback
     *
     * @return mixed
     *
     * @throws \Throwable
     */
    protected function traceExecute(callable $callback)
    {
        if (!$this->spanTracer->hasActiveTrace()) {
            return $callback();
        }

        $span = new Span($this->buildSpanName($this->sql), SpanBundle::SPAN_TYPE_DB, $this->databaseType);

        $this->fillSpanContext($span, $this->sql);

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
