<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;

abstract class AbstractTracingMiddleware
{
    use SpanTracerAwareTrait;

    public function __construct(SpanTracer $spanTracer)
    {
        $this->spanTracer = $spanTracer;
    }
}
