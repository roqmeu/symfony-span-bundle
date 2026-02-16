<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\State;

class Trace
{
    protected string $id;

    protected ?string $parentId = null;

    protected ?Span $span;

    public function __construct(?Span $span = null, ?string $id = null)
    {
        $this->setSpan($span);

        $this->id = $id ?? \bin2hex(\random_bytes(16));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParent(): ?string
    {
        return $this->parentId;
    }

    public function setParent(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function setSpan(?Span $span): void
    {
        $this->span = $span;

        if ($span !== null) {
            $span->setTrace($this);
        }
    }

    public function getSpan(): ?Span
    {
        return $this->span;
    }
}
