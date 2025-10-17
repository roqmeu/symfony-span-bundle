<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Transaction;

class NullSpanInteractor implements SpanInteractor
{
    public function beginCurrentTransaction(): ?Transaction
    {
        return null;
    }

    public function beginTransaction(): ?Transaction
    {
        return null;
    }

    public function getCurrentTransaction(): ?Transaction
    {
        return null;
    }

    public function endTransaction(?Transaction $trace): void
    {
    }

    public function beginCurrentSpan(): ?Span
    {
        return null;
    }

    public function beginSpan(): ?Span
    {
        return null;
    }

    public function getCurrentSpan(): ?Span
    {
        return null;
    }

    public function endSpan(?Transaction $trace): void
    {
    }
}
