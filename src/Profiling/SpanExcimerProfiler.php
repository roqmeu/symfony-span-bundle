<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Profiling;

use Psr\Log\LoggerInterface;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;
use Roqmeu\SpanBundle\State\Span;
use Symfony\Contracts\Service\ResetInterface;

class SpanExcimerProfiler implements SpanProfiler, ResetInterface
{
    use SpanTracerAwareTrait;

    private const MAX_DEPTH = 64;
    private const MAX_STACKTRACE = 32;

    private ?LoggerInterface $logger;

    private float $period;

    /** @var \ExcimerProfiler[] */
    private array $profilers = [];

    /** @var float[] */
    private array $profilersStarts = [];

    public function __construct(SpanTracer $spanTracer, ?LoggerInterface $logger, float $period)
    {
        $this->spanTracer = $spanTracer;
        $this->logger = $logger;
        $this->period = $period;
    }

    public function reset(): void
    {
        $this->profilers = [];
        $this->profilersStarts = [];
    }

    public function has(string $name): bool
    {
        return isset($this->profilers[$name]);
    }

    public function start(string $name): void
    {
        if (isset($this->profilers[$name])) {
            if ($this->logger !== null) {
                $this->logger->warning('SpanProfiler is already running', [
                    'name' => $name,
                ]);
            }

            return;
        }

        try {
            $prof = new \ExcimerProfiler();
            $prof->setEventType(EXCIMER_REAL);
            $prof->setPeriod($this->period);
            $prof->setMaxDepth(self::MAX_DEPTH);

            $prof->start();
        } catch (\Throwable $exception) {
            if ($this->logger !== null) {
                $this->logger->warning('Error while starting ext-excimer SpanProfiler', [
                    'exception' => $exception,
                    'name' => $name,
                ]);
            }

            return;
        }

        $this->profilersStarts[$name] = \microtime(true);
        $this->profilers[$name] = $prof;
    }

    public function stop(string $name): void
    {
        $profiler = $this->profilers[$name] ?? null;

        if ($profiler === null) {
            if ($this->logger !== null) {
                $this->logger->warning('SpanProfiler not found for stopping', [
                    'name' => $name,
                ]);
            }

            return;
        }

        $profiler->stop();
    }

    public function send(string $name): void
    {
        $profiler = $this->profilers[$name] ?? null;

        if ($profiler === null) {
            if ($this->logger !== null) {
                $this->logger->warning('SpanProfiler not found for sending', [
                    'name' => $name,
                ]);
            }

            return;
        }

        if (!$this->spanTracer->hasActiveTrace()) {
            return;
        }

        $span = $this->speedscopeToSpan($profiler->getLog()->getSpeedscopeData(), $this->profilersStarts[$name]);

        unset($this->profilers[$name], $this->profilersStarts[$name]);

        if ($span === null || $span->getChildren() === []) {
            return;
        }

        $this->spanTracer->startSpan($span);

        foreach ($span->getChildren() as $childSpan) {
            $this->spanTracer->startSpan($childSpan);
            $this->spanTracer->endSpan($childSpan);
        }

        $this->spanTracer->endSpan($span);
    }

    private function speedscopeToSpan(array $speedscopeData, float $start): ?Span
    {
        $samples = $speedscopeData['profiles'][0]['samples'] ?? null;
        $weights = $speedscopeData['profiles'][0]['weights'] ?? null;

        if ($samples === null || $weights === null || \count($samples) === 0 || \count($weights) === 0) {
            return null;
        }

        $frames = $speedscopeData['shared']['frames'];
        $samplesCount = \count($samples);

        $parent = new Span(SpanBundle::SPAN_TYPE_INTERNAL, SpanBundle::SPAN_SUBTYPE_PROFILE);
        $parent->setStartTime($start);

        $nextIdx = 0;
        $nextSample = $samples[$nextIdx];
        $nextNameIdx = $nextSample[\array_key_last($nextSample)];

        do {
            $idx = $nextIdx;
            $sample = $nextSample;
            $nameIdx = $nextNameIdx;

            $duration = $weights[$idx];
            $stacktrace = [];

            for ($stacktraceIdx = \count($sample) - 1; $stacktraceIdx >= 0; $stacktraceIdx--) {
                $name = $frames[$sample[$stacktraceIdx]]['name'];
                $stacktrace[] = $name;

                if (\count($stacktrace) >= self::MAX_STACKTRACE) {
                    break;
                }
            }

            $nextIdx = $idx + 1;

            for (; $nextIdx < $samplesCount; $nextIdx++) {
                $nextSample = $samples[$nextIdx];
                $nextNameIdx = $nextSample[\array_key_last($nextSample)];

                if ($nameIdx === $nextNameIdx) {
                    $duration += $weights[$nextIdx];
                } else {
                    break;
                }
            }

            $duration *= 0.001 * 0.001 * 0.001;

            if ($duration > $this->period) {
                $span = new Span(SpanBundle::SPAN_TYPE_INTERNAL, SpanBundle::SPAN_SUBTYPE_PROFILE);

                $span->setStartTime($start);
                $span->context->profile = ['stacktrace' => $stacktrace];

                $parent->addChild($span);

                $start += $duration;

                $span->setEndTime($start);
            } else {
                $start += $duration;
            }
        } while ($nextIdx < $samplesCount);

        $parent->setEndTime($start);

        return $parent;
    }
}
