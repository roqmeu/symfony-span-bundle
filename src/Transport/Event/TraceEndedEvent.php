<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Transport\Event;

use Roqmeu\SpanBundle\State\Trace;

class TraceEndedEvent
{
    public Trace $trace;

    public function __construct(Trace $trace)
    {
        $this->trace = $trace;
    }
}
