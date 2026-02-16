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

    public function startTraceSpan(Span $span, ?\Closure $propagationExtractor = null): void
    {
        $trace = new Trace($span);

        $this->spanInteractor->startActiveTrace($trace);

        $this->startSpanInternal($span, $trace, null, $propagationExtractor);
    }

    public function startSpan(Span $span, ?\Closure $propagationInjector = null, ?Trace $trace = null): void
    {
        $this->startSpanInternal($span, ($trace ?? $span->getTrace()) ?? $this->spanInteractor->getActiveTrace(), $propagationInjector, null);
    }

    protected function startSpanInternal(Span $span, ?Trace $trace, ?\Closure $propagationInjector, ?\Closure $propagationExtractor): void
    {
        if ($trace !== null && $trace !== $span->getTrace()) {
            $span->setTrace($trace);

            $traceSpan = $trace->getSpan();

            if ($traceSpan !== null && $traceSpan !== $span && $span->getParent() === null) {
                $traceSpan->addChild($span);
            }
        }

        $this->spanInteractor->startSpan($span, $propagationInjector, $propagationExtractor);
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
