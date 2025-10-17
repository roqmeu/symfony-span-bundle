<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\State;

use Symfony\Contracts\Service\ResetInterface;

class TransactionPool implements ResetInterface
{
    private ?Transaction $current = null;

    /**
     * @var Transaction[]
     */
    private array $pool = [];

    public function getCurrentTransaction(): ?Transaction
    {
        return $this->current;
    }

    public function getTransaction(int $id): ?Transaction
    {
        return $this->pool[$id] ?? null;
    }

    public function addCurrentTransaction(int $id, Transaction $trace): void
    {
        $this->pool[$id] = $trace;

        $this->current = $trace;
    }

    public function addTransaction(int $id, Transaction $trace): void
    {
        $this->pool[$id] = $trace;
    }

    public function removeTransaction(int $id): void
    {
        $trace = $this->pool[$id] ?? null;

        if ($trace === null) {
            return;
        }

        unset($this->pool[$id]);

        if ($trace === $this->current) {
            $lastId = array_key_last($this->pool);

            $this->current = $lastId !== null ? $this->pool[$lastId] : null;
        }
    }

    public function empty(): bool
    {
        return count($this->pool) === 0;
    }

    public function reset(): void
    {
        $this->current = null;
        $this->pool = [];
    }
}
