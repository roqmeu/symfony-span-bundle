<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Trace;

interface SpanTracer
{
    public function getActiveTrace(): ?Trace;

    public function hasActiveTrace(): bool;

    public function startSpanWithTrace(Span $span, ?string $traceId = null, ?string $traceParentId = null): void;

    public function startSpan(Span $span, ?Trace $trace = null): void;

    public function endSpan(Span $span): void;
}
