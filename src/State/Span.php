<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\State;

use Roqmeu\SpanBundle\SpanBundle;

class Span
{
    /**
     * @var string
     *
     * @see SpanBundle::SPAN_TYPE_*
     */
    protected string $type;

    /**
     * @var string|null
     *
     * @see SpanBundle::SPAN_SUBTYPE_*
     */
    protected ?string $subtype;

    /**
     * @var float|null start timestamp with microseconds
     */
    protected ?float $start = null;

    /**
     * @var float|null end timestamp with microseconds
     */
    protected ?float $end = null;

    protected ?Trace $trace = null;

    protected ?Span $parent = null;

    /**
     * @var array<int, Span>
     */
    protected array $children = [];

    protected bool $successful = true;

    protected ?\Throwable $error = null;

    public Context $context;

    public function __construct(string $type, string $subtype = null)
    {
        $this->type = $type;
        $this->subtype = $subtype;

        $this->context = new Context();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    public function setSubtype(?string $subtype): void
    {
        $this->subtype = $subtype;
    }

    public function getStartTime(): ?float
    {
        return $this->start;
    }

    public function setStartTime(?float $startTime): void
    {
        $this->start = $startTime;
    }

    public function getEndTime(): ?float
    {
        return $this->end;
    }

    public function setEndTime(?float $endTime): void
    {
        $this->end = $endTime;
    }

    public function isEnded(): bool
    {
        return $this->end !== null;
    }

    public function getTrace(): ?Trace
    {
        return $this->trace;
    }

    public function setTrace(?Trace $trace): void
    {
        $this->trace = $trace;
    }

    public function getTraceSpan(): ?Span
    {
        return $this->trace !== null ? $this->trace->getSpan() : null;
    }

    public function getParent(): ?Span
    {
        return $this->parent;
    }

    public function setParent(Span $span): void
    {
        $this->parent = $span;
    }

    /**
     * @return array<int, Span>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(Span $span): void
    {
        $span->setParent($this);

        $span->setTrace($this->getTrace());

        $this->children[] = $span;
    }

    /**
     * Iterate children spans in pre-order DFS
     *
     * @return \Generator<Span>
     */
    public function iterateChildrenDfs(): \Generator
    {
        $stack = \array_reverse($this->children);

        while ($stack !== []) {
            /** @var Span $current */
            $current = \array_pop($stack);

            yield $current;

            $children = $current->getChildren();

            for ($i = \count($children) - 1; $i >= 0; --$i) {
                $stack[] = $children[$i];
            }
        }
    }

    /**
     * Iterate children spans in Euler tour order (enter + exit)
     *
     * @return \Generator<Span>
     */
    public function iterateChildrenEuler(): \Generator
    {
        $stack = \array_reverse($this->children);

        $previous = $this;

        while ($stack !== []) {
            $current = \end($stack);

            yield $current;

            $children = $current->getChildren();
            $childrenCount = \count($children);

            if ($childrenCount === 0) {
                yield \array_pop($stack);
            } elseif ($current === $previous->getParent()) {
                \array_pop($stack);
            } else {
                for ($i = $childrenCount - 1; $i >= 0; --$i) {
                    $stack[] = $children[$i];
                }
            }

            $previous = $current;
        }
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function setSuccessful(bool $successful): void
    {
        $this->successful = $successful;
    }

    public function setSuccessfulIf(bool $successful): void
    {
        if ($this->successful) {
            $this->successful = $successful;
        }
    }

    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    public function setError(\Throwable $error): void
    {
        $this->error = $error;

        $this->successful = false;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }
}
