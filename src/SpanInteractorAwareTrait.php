<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

trait SpanInteractorAwareTrait
{
    /**
     * @var SpanInteractor|null
     */
    protected $spanInteractor = null;

    /**
     * @required
     */
    public function setSpanInteractor(SpanInteractor $spanInteractor): void
    {
        $this->spanInteractor = $spanInteractor;
    }

    public function getSpanInteractor(): SpanInteractor
    {
        if ($this->spanInteractor === null) {
            $this->setNullInteractor();
        }

        return $this->spanInteractor;
    }

    public function setNullInteractor(): void
    {
        $this->spanInteractor = new NullSpanInteractor();
    }
}
