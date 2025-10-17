<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanInteractorAwareTrait;
use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Transaction;

trait TransactionTracingTrait
{
    use SpanInteractorAwareTrait;

    protected function transactionStart(?int $idx, ?string $name, string $type, bool $forced = false): Transaction
    {
        if ($name === null || $name === '') {
            $name = SpanBundle::UNKNOWN;
        }

        $transaction = new Transaction($name, $type);

        $this->getSpanInteractor()->beginCurrentTransaction($transaction, $idx);

        if ($forced) {
            $this->tracePool->addCurrentTransaction($idx, $trace);
        }

        return $transaction;
    }

    protected function transactionError(int $idx, ?\Throwable $error): void
    {
        $span = $this->getSpanInteractor()->getCurrentTransaction();

        if ($span === null) {
            return;
        }

        $span->error = $error;
        $span->successful = false;
    }

    protected function getTransaction(int $idx): ?Span
    {
        return $this->spanPool->get($idx);
    }

    protected function transactionEnd(int $idx): void
    {
        $span = $this->spanPool->get($idx);

        if ($span === null) {
            return;
        }

        $span->end();
        $this->dispatcher->spanFinished($span);
        $this->spanPool->drop($idx);

        if ($span->parent === null && $span->transaction !== null) {
            $this->dispatcher->traceFinished($span->transaction);
            $this->tracePool->removeTransaction($idx);
        }
    }
}
