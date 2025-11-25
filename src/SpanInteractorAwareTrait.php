<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

trait SpanInteractorAwareTrait
{
    protected SpanInteractor $spanInteractor;

    public function setSpanInteractor(SpanInteractor $spanInteractor): void
    {
        $this->spanInteractor = $spanInteractor;
    }

    public function getSpanInteractor(): SpanInteractor
    {
        return $this->spanInteractor;
    }
}
