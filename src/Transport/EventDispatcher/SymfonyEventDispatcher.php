<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Transport\EventDispatcher;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Trace;
use Roqmeu\SpanBundle\Transport\Event\SpanEndedEvent;
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceEndedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceStartedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SymfonyEventDispatcher implements EventDispatcher
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function traceStarted(Trace $trace): void
    {
        $this->eventDispatcher->dispatch(new TraceStartedEvent($trace));
    }

    public function traceEnded(Trace $trace): void
    {
        $this->eventDispatcher->dispatch(new TraceEndedEvent($trace));
    }

    public function spanStarted(Span $span): void
    {
        $this->eventDispatcher->dispatch(new SpanStartedEvent($span));
    }

    public function spanEnded(Span $span): void
    {
        $this->eventDispatcher->dispatch(new SpanEndedEvent($span));
    }
}
