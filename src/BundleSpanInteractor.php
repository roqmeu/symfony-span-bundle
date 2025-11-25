<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Trace;
use Roqmeu\SpanBundle\Transport\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Service\ResetInterface;

class BundleSpanInteractor implements SpanInteractor, ResetInterface
{
    protected EventDispatcher $eventDispatcher;

    protected ?Trace $activeTrace = null;

    /**
     * @var Trace[]
     */
    protected array $activeTraceStack = [];

    public function __construct(EventDispatcher $spanDispatcher)
    {
        $this->eventDispatcher = $spanDispatcher;
    }

    public function startActiveTrace(Trace $trace): void
    {
        $this->activeTraceStack[] = $this->activeTrace = $trace;

        $this->eventDispatcher->traceStarted($trace);
    }

    public function startTrace(Trace $trace): void
    {
        $this->eventDispatcher->traceStarted($trace);
    }

    public function getActiveTrace(): ?Trace
    {
        return $this->activeTrace;
    }

    public function endTrace(Trace $trace): void
    {
        $this->eventDispatcher->traceEnded($trace);

        $activeTraceStackIndex = \array_search($trace, $this->activeTraceStack, true);

        if ($activeTraceStackIndex !== false) {
            unset($this->activeTraceStack[$activeTraceStackIndex]);
        }

        if ($this->activeTrace === $trace) {
            if (\count($this->activeTraceStack) > 0) {
                $this->activeTrace = $this->activeTraceStack[\array_key_last($this->activeTraceStack)];
            } else {
                $this->activeTrace = null;
            }
        }
    }

    public function startSpan(Span $span): void
    {
        if ($span->getStartTime() === null) {
            $span->setStartTime(\microtime(true));
        }

        $this->eventDispatcher->spanStarted($span);
    }

    public function endSpan(Span $span): void
    {
        if ($span->getEndTime() === null) {
            $span->setEndTime(\microtime(true));
        }

        $this->eventDispatcher->spanEnded($span);
    }

    public function reset(): void
    {
        $this->activeTrace = null;

        $this->activeTraceStack = [];
    }
}
