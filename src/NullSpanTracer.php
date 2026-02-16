<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Trace;

class NullSpanTracer implements SpanTracer
{
    use SpanInteractorAwareTrait;

    public function getActiveTrace(): ?Trace
    {
        return null;
    }

    public function hasActiveTrace(): bool
    {
        return false;
    }

    public function startTraceSpan(Span $span, ?\Closure $propagationExtractor = null): void
    {
    }

    public function startSpan(Span $span, ?\Closure $propagationInjector = null, ?Trace $trace = null): void
    {
    }

    public function endSpan(Span $span): void
    {
    }
}
