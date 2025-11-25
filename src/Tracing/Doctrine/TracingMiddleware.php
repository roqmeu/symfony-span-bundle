<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;

class TracingMiddleware implements Middleware
{
    use SpanTracerAwareTrait;

    public function __construct(SpanTracer $spanTracer)
    {
        $this->spanTracer = $spanTracer;
    }

    public function wrap(Driver $driver): Driver
    {
        return new TracingDriver($this->spanTracer, $driver);
    }
}
