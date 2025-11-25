<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use Roqmeu\SpanBundle\DependencyInjection\SpanExtension;
use Roqmeu\SpanBundle\Profiling\SpanExcimerProfiler;
use Roqmeu\SpanBundle\Profiling\SpanNullProfiler;
use Roqmeu\SpanBundle\Profiling\SpanProfiler;
use Roqmeu\SpanBundle\Profiling\SpanProfilerHandler;
use Roqmeu\SpanBundle\Transport\Event\TraceEndedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceStartedEvent;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ProfilerMiddlewarePass implements CompilerPassInterface
{
    public const PROFILER_THRESHOLD_DEFAULT = 0.1;
    public const PROFILER_THRESHOLD_MIN = 0.01;

    public const PROFILER_ALLOWED_TYPES_DEFAULT = [];
    public const PROFILER_IGNORED_TYPES_DEFAULT = [];

    public const PROFILER_ALLOWED_SUBTYPES_DEFAULT = [];
    public const PROFILER_IGNORED_SUBTYPES_DEFAULT = [];

    public function process(ContainerBuilder $container): void
    {
        $config = SpanExtension::getConfig($container);

        if ($config === null || $config['enabled'] !== true || $config['profiling_enabled'] !== true) {
            $container->register(SpanProfiler::class, SpanNullProfiler::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);

            return;
        }

        if (!\extension_loaded('excimer') || !\class_exists('\ExcimerProfiler')) {
            $container->register(SpanProfiler::class, SpanNullProfiler::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);

            return;
        }

        $profilerThreshold = $config['profiling_threshold'] ?? 0.0;

        if ($profilerThreshold < self::PROFILER_THRESHOLD_MIN) {
            $profilerThreshold = self::PROFILER_THRESHOLD_MIN;
        }

        if (!$container->hasDefinition(SpanProfiler::class)) {
            $container->register(SpanProfiler::class, SpanExcimerProfiler::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setArguments(['$period' => $profilerThreshold / 2]);
        }

        $profilerAllowedTypes = $config['profiling_allowed_types'] ?? null;
        $profilerIgnoredTypes = $config['profiling_ignored_types'] ?? null;

        $profilerAllowedSubtypes = $config['profiling_allowed_subtypes'] ?? null;
        $profilerIgnoredSubtypes = $config['profiling_ignored_subtypes'] ?? null;

        if ($profilerAllowedTypes !== null) {
            $profilerIgnoredTypes = null;
        }

        if ($profilerAllowedSubtypes !== null) {
            $profilerIgnoredSubtypes = null;
        }

        if (!$container->hasDefinition(SpanProfilerHandler::class)) {
            $container->register(SpanProfilerHandler::class, SpanProfilerHandler::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->setArguments(
                    ['$enabled' => '%span.profiling_enabled%', '$allowedTypes' => $profilerAllowedTypes, '$ignoredTypes' => $profilerIgnoredTypes, '$allowedSubtypes' => $profilerAllowedSubtypes, '$ignoredSubtypes' => $profilerIgnoredSubtypes]
                )
                ->addTag('kernel.event_listener', ['event' => TraceStartedEvent::class, 'method' => 'onTraceStarted', 'priority' => -256])
                ->addTag('kernel.event_listener', ['event' => TraceEndedEvent::class, 'method' => 'onTraceEnded', 'priority' => 256]);
        }
    }
}
