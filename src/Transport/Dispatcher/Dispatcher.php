<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Transport\Dispatcher;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Transaction;

interface Dispatcher
{
    public function spanStarted(Span $span): void;

    public function spanFinished(Span $span): void;

    public function traceStarted(Transaction $trace): void;

    public function traceFinished(Transaction $trace): void;
}
