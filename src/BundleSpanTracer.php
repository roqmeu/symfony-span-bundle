<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Trace;

class BundleSpanTracer implements SpanTracer
{
    use SpanInteractorAwareTrait;

    public function __construct(SpanInteractor $spanInteractor)
    {
        $this->spanInteractor = $spanInteractor;
    }

    public function getActiveTrace(): ?Trace
    {
        return $this->spanInteractor->getActiveTrace();
    }

    public function hasActiveTrace(): bool
    {
        return $this->spanInteractor->getActiveTrace() !== null;
    }

    public function startSpan(Span $span, ?Trace $trace = null): void
    {
        if ($span->getTrace() === null) {
            $span->setTrace($trace ?? $this->spanInteractor->getActiveTrace());
        }

        $trace = $span->getTrace();

        $traceSpan = $trace !== null ? $trace->getSpan() : null;

        if ($trace !== null && $traceSpan !== null && $traceSpan !== $span && $span->getParent() === null) {
            $traceSpan->addChild($span);
        }

        $this->spanInteractor->startSpan($span);
    }

    public function startSpanWithTrace(Span $span, ?string $traceId = null, ?string $traceParentId = null): void
    {
        $trace = new Trace($span, $traceId);

        $trace->setParent($traceParentId);

        $this->spanInteractor->startActiveTrace($trace);

        $this->startSpan($span);
    }

    public function endSpan(Span $span): void
    {
        $this->spanInteractor->endSpan($span);

        $trace = $span->getTrace();

        if ($trace !== null && $span === $trace->getSpan()) {
            $this->spanInteractor->endTrace($trace);
        }
    }
}
