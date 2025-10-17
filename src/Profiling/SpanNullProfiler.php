<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Profiling;

class SpanNullProfiler implements SpanProfiler
{
    public function start(string $name): void
    {
    }

    public function stop(string $name): void
    {
    }

    public function send(string $name): void
    {
    }
}
