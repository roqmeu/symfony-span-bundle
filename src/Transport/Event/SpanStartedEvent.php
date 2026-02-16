<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Transport\Event;

use Roqmeu\SpanBundle\State\Span;

class SpanStartedEvent
{
    public Span $span;

    public ?\Closure $propagationInjector;

    public ?\Closure $propagationExtractor;

    public function __construct(Span $span, ?\Closure $propagationInjector = null, ?\Closure $propagationExtractor = null)
    {
        $this->span = $span;
        $this->propagationInjector = $propagationInjector;
        $this->propagationExtractor = $propagationExtractor;
    }
}
