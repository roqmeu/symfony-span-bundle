<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

class TracingMiddlewareV3 extends AbstractTracingMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new TracingDriverV3($this->spanTracer, $driver);
    }
}
