<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Profiling;

interface SpanProfiler
{
    public function has(string $name): bool;

    public function start(string $name): void;

    public function stop(string $name): void;

    public function send(string $name): void;
}
