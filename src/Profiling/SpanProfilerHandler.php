<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Profiling;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\Transport\Event\TraceEndedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceStartedEvent;

class SpanProfilerHandler
{
    public bool $enabled = false;

    private ?SpanProfiler $profiler;

    private ?array $allowedTypes;
    private ?array $ignoredTypes;

    private ?array $allowedSubtypes;
    private ?array $ignoredSubtypes;

    public function __construct(
        bool $enabled = false,
        ?SpanProfiler $profiler = null,
        ?array $allowedTypes = [],
        ?array $ignoredTypes = [],
        ?array $allowedSubtypes = [],
        ?array $ignoredSubtypes = []
    ) {
        $this->enabled = $enabled;

        $this->profiler = $profiler;

        $this->allowedTypes = $allowedTypes;
        $this->ignoredTypes = $ignoredTypes;

        $this->allowedSubtypes = $allowedSubtypes;
        $this->ignoredSubtypes = $ignoredSubtypes;
    }

    public function onTraceStarted(TraceStartedEvent $event): void
    {
        if ($this->enabled && $this->profiler !== null) {
            $span = $event->trace->getSpan();

            if ($span !== null && $this->isAllowedSpan($span)) {
                $this->profiler->start(\spl_object_hash($span));
            }
        }
    }

    public function onTraceEnded(TraceEndedEvent $event): void
    {
        if ($this->enabled && $this->profiler !== null) {
            $span = $event->trace->getSpan();

            if ($span !== null && $this->profiler->has(\spl_object_hash($span))) {
                $this->profiler->stop(\spl_object_hash($span));

                $this->profiler->send(\spl_object_hash($span));
            }
        }
    }

    private function isAllowedSpan(Span $span): bool
    {
        if ($this->allowedTypes !== null) {
            if (!\in_array($span->getType(), $this->allowedTypes, true)) {
                return false;
            }
        } elseif ($this->ignoredTypes !== null) {
            if (\in_array($span->getType(), $this->ignoredTypes, true)) {
                return false;
            }
        }

        if ($this->allowedSubtypes !== null) {
            if (!\in_array($span->getSubtype(), $this->allowedSubtypes, true)) {
                return false;
            }
        } elseif ($this->ignoredSubtypes !== null) {
            if (\in_array($span->getSubtype(), $this->ignoredSubtypes, true)) {
                return false;
            }
        }

        return true;
    }
}
