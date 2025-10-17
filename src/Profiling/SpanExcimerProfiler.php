<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Profiling;

use Psr\Log\LoggerInterface;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;
use Symfony\Contracts\Service\ResetInterface;

class SpanExcimerProfiler implements SpanProfiler, ResetInterface
{
    private const MAX_DEPTH = 64;
    private const MAX_STACKTRACE = 32;

    private Dispatcher $dispatcher;
    private TransactionPool $tracePool;

    private ?LoggerInterface $logger;

    private float $period;

    /** @var \ExcimerProfiler[] */
    private array $profilers = [];

    /** @var float[] */
    private array $profilersStarts = [];

    public function __construct(
        Dispatcher $dispatcher,
        TransactionPool $tracePool,
        ?LoggerInterface $logger,
        float $period
    ) {
        $this->dispatcher = $dispatcher;
        $this->tracePool = $tracePool;
        $this->logger = $logger;
        $this->period = $period;
    }

    public function reset(): void
    {
        $this->profilers = [];
        $this->profilersStarts = [];
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

        $parent = $this->tracePool->current;

        if ($parent === null) {
            return;
        }

        $span = $this->speedscopeToSpan($profiler->getLog()->getSpeedscopeData(), $this->profilersStarts[$name]);

        unset($this->profilers[$name], $this->profilersStarts[$name]);

        if ($span === null) {
            return;
        }

        $this->dispatcher->spanStarted($span);

        foreach ($span->children as $child) {
            $this->dispatcher->spanStarted($child);
            $this->dispatcher->spanFinished($child);
        }

        $this->dispatcher->spanFinished($span);
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

        $parent = new Span('SpanProfiler', SpanBundle::SPAN_TYPE_INTERNAL, SpanBundle::SPAN_SUBTYPE_PROFILE);
        $parent->start = $start;

        $nextIdx = 0;
        $nextSample = $samples[$nextIdx];
        $nextNameIdx = $nextSample[\array_key_last($nextSample)];

        do {
            $idx = $nextIdx;
            $sample = $nextSample;
            $nameIdx = $nextNameIdx;

            $duration = $weights[$idx];
            $stacktrace = [];

            for ($stacktraceIdx = \count($sample) - 2; $stacktraceIdx >= 0; $stacktraceIdx--) {
                $name = $frames[$sample[$stacktraceIdx]]['name'];
                $stacktrace[] = $name;

                if (\count($stacktrace) >= self::MAX_STACKTRACE) {
                    break;
                }
            }

            for ($nextIdx = $idx + 1; $nextIdx < $samplesCount; $nextIdx++) {
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
                $span = new Span(
                    $frames[$nameIdx]['name'],
                    SpanBundle::SPAN_TYPE_INTERNAL,
                    SpanBundle::SPAN_SUBTYPE_PROFILE
                );
                $span->start = $start;
                $span->context->profile = ['stacktrace' => $stacktrace];

                $parent->addSpan($span);

                $start += $duration;

                $span->end($start);
            } else {
                $start += $duration;
            }
        } while ($nextIdx < $samplesCount);

        $parent->end($start);

        return $parent;
    }
}
