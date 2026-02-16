<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Trace;
use Roqmeu\SpanBundle\Transport\Event\SpanEndedEvent;
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceEndedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceStartedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

class BundleSpanInteractor implements SpanInteractor, ResetInterface
{
    protected EventDispatcherInterface $eventDispatcher;

    protected ?Trace $activeTrace = null;

    /**
     * @var Trace[]
     */
    protected array $activeTraceStack = [];

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function startActiveTrace(Trace $trace): void
    {
        $this->activeTraceStack[] = $this->activeTrace = $trace;

        $this->startTrace($trace);
    }

    public function startTrace(Trace $trace): void
    {
        $this->eventDispatcher->dispatch(new TraceStartedEvent($trace));
    }

    public function getActiveTrace(): ?Trace
    {
        return $this->activeTrace;
    }

    public function endTrace(Trace $trace): void
    {
        $this->eventDispatcher->dispatch(new TraceEndedEvent($trace));

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

    public function startSpan(Span $span, ?\Closure $propagationInjector = null, ?\Closure $propagationExtractor = null): void
    {
        if ($span->getStartTime() === null) {
            $span->setStartTime(\microtime(true));
        }

        $this->eventDispatcher->dispatch(new SpanStartedEvent($span, $propagationInjector, $propagationExtractor));
    }

    public function endSpan(Span $span): void
    {
        if ($span->getEndTime() === null) {
            $span->setEndTime(\microtime(true));
        }

        $this->eventDispatcher->dispatch(new SpanEndedEvent($span));
    }

    public function reset(): void
    {
        $this->activeTrace = null;

        $this->activeTraceStack = [];
    }
}
