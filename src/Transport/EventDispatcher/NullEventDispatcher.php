<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Transport\EventDispatcher;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Trace;

class NullEventDispatcher implements EventDispatcher
{
    public function traceStarted(Trace $trace): void
    {
    }

    public function traceEnded(Trace $trace): void
    {
    }

    public function spanStarted(Span $span): void
    {
    }

    public function spanEnded(Span $span): void
    {
    }
}
