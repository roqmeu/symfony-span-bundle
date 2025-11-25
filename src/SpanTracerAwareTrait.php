<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

trait SpanTracerAwareTrait
{
    protected SpanTracer $spanTracer;

    public function setSpanTracer(SpanTracer $spanTracer): void
    {
        $this->spanTracer = $spanTracer;
    }

    public function getSpanTracer(): SpanTracer
    {
        return $this->spanTracer;
    }
}
