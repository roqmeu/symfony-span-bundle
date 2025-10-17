<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Transport\Event;

use Roqmeu\SpanBundle\State\Transaction;

class TraceFinishedEvent
{
    public Transaction $trace;

    public function __construct(
        Transaction $trace
    ) {
        $this->trace = $trace;
    }
}
