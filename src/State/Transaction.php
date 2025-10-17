<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\State;

class Transaction
{
    /**
     * @var string[] $parentTraces Parent Trace ids
     */
    public array $parentTraces = [];

    /**
     * @var string|null $currentTrace Current Trace id
     */
    public ?string $currentTrace = null;

    /**
     * @var string[] $parentSpans Parent Span ids
     */
    public array $parentSpans = [];

    /**
     * @var Span|null $currentSpan Current Span
     */
    public ?Span $currentSpan = null;

    /**
     * @var Span[] $childrenSpan Children Spans
     */
    public array $childrenSpan = [];

    public string $name;

    /**
     * @var string
     *
     * @see SpanBundle::TRANSACTION_TYPE_*
     */
    public string $type;

    /**
     * @var float start time in seconds
     */
    public float $start;

    /**
     * @var float|null end time in seconds
     */
    public ?float $end = null;

    public ?bool $successful = null;

    public bool $canceled = false;

    public TransactionContext $context;

    public ?\Throwable $error = null;

    public function __construct(
        string $name = '',
        string $type = '',
        ?float $start = null
    ) {
        $this->name = $name;
        $this->type = $type;

        $this->start = $start ?? microtime(true);

        $this->context = new TransactionContext();
    }

    public function addCurrentSpan(Span $span): Span
    {
        $span->transaction = $this;

        $this->childrenSpan[] = $span;
        $this->currentSpan = $span;

        return $span;
    }

    public function addSpan(Span $span): Span
    {
        $span->transaction = $this;

        $this->childrenSpan[] = $span;

        return $span;
    }
}
