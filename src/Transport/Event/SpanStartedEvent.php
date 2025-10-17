<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Transport\Event;

use Roqmeu\SpanBundle\State\Span;

class SpanStartedEvent
{
    public Span $span;

    public function __construct(
        Span $span
    ) {
        $this->span = $span;
    }
}
