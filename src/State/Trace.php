<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\State;

class Trace
{
    protected string $id;

    protected ?Span $rootSpan;

    protected ?Trace $parent = null;

    public function __construct(?Span $rootSpan = null)
    {
        $this->setRootSpan($rootSpan);

        $this->id = \bin2hex(\random_bytes(16));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParent(): ?Trace
    {
        return $this->parent;
    }

    public function setParent(?Trace $parent): void
    {
        $this->parent = $parent;
    }

    public function setRootSpan(?Span $rootSpan): void
    {
        $this->rootSpan = $rootSpan;

        if ($rootSpan !== null) {
            $rootSpan->setTrace($this);
        }
    }

    public function getRootSpan(): ?Span
    {
        return $this->rootSpan;
    }
}
