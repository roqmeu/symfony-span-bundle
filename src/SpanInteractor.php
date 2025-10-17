<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Transaction;

interface SpanInteractor
{
    public function beginCurrentTransaction(Transaction $transaction, ?int $idx = null): int;

    public function beginTransaction(Transaction $transaction, ?int $idx = null): int;

    public function getCurrentTransaction(): ?Transaction;

    public function getTransaction(int $idx): ?Transaction;

    public function endTransaction(?Transaction $trace): void;

    public function beginCurrentSpan(): ?Span;

    public function beginSpan(): ?Span;

    public function getCurrentSpan(): ?Span;

    public function endSpan(?Span $span): void;
}
