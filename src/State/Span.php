<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\State;

use Roqmeu\SpanBundle\SpanBundle;

class Span
{
    public string $name;

    /**
     * @var string
     *
     * @see SpanBundle::SPAN_TYPE_*
     */
    public string $type;

    /**
     * @var string
     *
     * @see SpanBundle::SPAN_SUBTYPE_*
     */
    public string $subtype;

    /**
     * @var float start time in seconds
     */
    public float $start;

    /**
     * @var float|null end time in seconds
     */
    public ?float $end = null;

    public ?Transaction $transaction = null;

    public ?Span $parent = null;

    /**
     * @var Span[]
     */
    public array $children = [];

    public ?bool $successful = null;

    public bool $canceled = false;

    public SpanContext $context;

    public ?\Throwable $error = null;

    public function __construct(
        string $name = '',
        string $type = '',
        string $subtype = '',
        ?float $start = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->subtype = $subtype;

        $this->start = $start ?? microtime(true);

        $this->context = new SpanContext();
    }

    public function addCurrentSpan(Span $span): void
    {
        $span->transaction = $this->transaction;
        $span->parent = $this;

        $this->children[] = $span;

        if ($this->transaction !== null) {
            $this->transaction->currentSpan = $span;
        }
    }

    public function addSpan(Span $span): void
    {
        $span->transaction = $this->transaction;
        $span->parent = $this;

        $this->children[] = $span;
    }

    public function end(?float $end = null): void
    {
        $this->end = $end ?? microtime(true);

        if ($this->transaction !== null && $this === $this->transaction->currentSpan) {
            $this->transaction->currentSpan = $this->parent;
        }
    }

    public function ended(): bool
    {
        return $this->end !== null;
    }
}
