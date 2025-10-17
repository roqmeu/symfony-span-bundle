<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;

trait SpanTracingTrait
{
    protected Dispatcher $dispatcher;

    protected TransactionPool $tracePool;

    protected function beginCurrentSpan(Span $parent, string $name, string $type, string $subtype): Span
    {
        $span = $this->beginSpan($parent, $name, $type, $subtype);

        if ($parent->transaction !== null) {
            $parent->transaction->currentSpan = $span;
        }

        return $span;
    }

    protected function beginSpan(Span $parent, string $name, string $type, string $subtype): Span
    {
        $span = new Span($name, $type, $subtype);
        $parent->addSpan($span);
        $this->dispatcher->spanStarted($span);

        return $span;
    }

    protected function endSpan(Span $span): void
    {
        $span->end();
        $this->dispatcher->spanFinished($span);
    }

    protected function errorSpan(Span $span, ?\Throwable $error): void
    {
        $span->error = $error;
        $span->successful = false;
    }
}
