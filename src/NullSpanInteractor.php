<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Trace;

class NullSpanInteractor implements SpanInteractor
{
    public function startActiveTrace(Trace $trace): void
    {
    }

    public function startTrace(Trace $trace): void
    {
    }

    public function getActiveTrace(): ?Trace
    {
        return null;
    }

    public function endTrace(Trace $trace): void
    {
    }

    public function startSpan(Span $span): void
    {
    }

    public function endSpan(Span $span): void
    {
    }
}
