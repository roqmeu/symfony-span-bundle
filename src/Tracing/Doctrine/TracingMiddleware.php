<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;

class TracingMiddleware implements Middleware
{
    private Dispatcher $dispatcher;
    private TransactionPool $tracePool;

    public function __construct(Dispatcher $dispatcher, TransactionPool $tracePool)
    {
        $this->dispatcher = $dispatcher;
        $this->tracePool = $tracePool;
    }

    public function wrap(Driver $driver): Driver
    {
        return new TracingDriver($this->dispatcher, $this->tracePool, $driver);
    }
}
