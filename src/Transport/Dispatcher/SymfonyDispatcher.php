<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Transport\Dispatcher;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Transaction;
use Roqmeu\SpanBundle\Transport\Event\SpanFinishedEvent;
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceFinishedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceStartedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SymfonyDispatcher implements Dispatcher
{
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function spanStarted(Span $span): void
    {
        $this->eventDispatcher->dispatch(new SpanStartedEvent($span));
    }

    public function spanFinished(Span $span): void
    {
        $this->eventDispatcher->dispatch(new SpanFinishedEvent($span));
    }

    public function traceStarted(Transaction $trace): void
    {
        $this->eventDispatcher->dispatch(new TraceStartedEvent($trace));
    }

    public function traceFinished(Transaction $trace): void
    {
        $this->eventDispatcher->dispatch(new TraceFinishedEvent($trace));
    }
}
