<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection;

use Roqmeu\SpanBundle\Bridge\ElasticApmBridge;
use Roqmeu\SpanBundle\BundleSpanInteractor;
use Roqmeu\SpanBundle\BundleSpanTracer;
use Roqmeu\SpanBundle\NullSpanInteractor;
use Roqmeu\SpanBundle\NullSpanTracer;
use Roqmeu\SpanBundle\SpanInteractor;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\Tracing\Command\TracingCommandListener;
use Roqmeu\SpanBundle\Tracing\Controller\TracingRequestListener;
use Roqmeu\SpanBundle\Transport\Event\TraceEndedEvent;
use Roqmeu\SpanBundle\Transport\EventDispatcher\EventDispatcher;
use Roqmeu\SpanBundle\Transport\EventDispatcher\NullEventDispatcher;
use Roqmeu\SpanBundle\Transport\EventDispatcher\SymfonyEventDispatcher;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class SpanExtension extends Extension
{
    public const ALIAS = 'span';

    public const CONFIG_PARAMETER = '.span.build_config';

    public function getAlias(): string
    {
        return self::ALIAS;
    }

    public static function getConfig(ContainerBuilder $container): ?array
    {
        if ($container->hasParameter(self::CONFIG_PARAMETER)) {
            $config = $container->getParameter(self::CONFIG_PARAMETER);
        } else {
            $config = null;
        }

        return \is_array($config) ? $config : null;
    }

    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration(self::ALIAS);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $buildConfig = [];

        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $buildConfig['enabled'] = ($config['enabled'] ?? null) === true;

        $buildConfig['tracing_enabled'] = ($config['tracing']['enabled'] ?? null) === true;

        $isProfilingEnabled = $this->filterDynamicBooleanFlag($config['profiling']['enabled'] ?? null);
        $buildConfig['profiling_enabled'] = $this->resolveDynamicBooleanFlag($isProfilingEnabled, 'profiling.enabled');
        $buildConfig['profiling_threshold'] = $config['profiling']['threshold'] ?? 0.0;
        $buildConfig['profiling_allowed_types'] = ($config['profiling']['allowed_types'] ?? null) ?: null;
        $buildConfig['profiling_ignored_types'] = ($config['profiling']['ignored_types'] ?? null) ?: null;
        $buildConfig['profiling_allowed_subtypes'] = ($config['profiling']['allowed_subtypes'] ?? null) ?: null;
        $buildConfig['profiling_ignored_subtypes'] = ($config['profiling']['ignored_subtypes'] ?? null) ?: null;

        $container->setParameter(self::CONFIG_PARAMETER, $buildConfig);

        $container->setParameter('span.profiling_enabled', $isProfilingEnabled);

        if ($buildConfig['enabled'] === true) {
            $container->register(SpanInteractor::class, BundleSpanInteractor::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);

            $container->register(SpanTracer::class, BundleSpanTracer::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);

            $container->register(EventDispatcher::class, SymfonyEventDispatcher::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
        } else {
            $container->register(SpanInteractor::class, NullSpanInteractor::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);

            $container->register(SpanTracer::class, NullSpanTracer::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);

            $container->register(EventDispatcher::class, NullEventDispatcher::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
        }

        if ($buildConfig['enabled'] === true && $buildConfig['tracing_enabled'] === true) {
            $container->register(TracingCommandListener::class, TracingCommandListener::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);

            $container->register(TracingRequestListener::class, TracingRequestListener::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
        }

        if ($buildConfig['enabled'] === true && $buildConfig['tracing_enabled'] === true) {
            $container->register(ElasticApmBridge::class, ElasticApmBridge::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->addTag('kernel.event_listener', ['event' => TraceEndedEvent::class, 'method' => 'onTraceEnded', 'priority' => -256]);
        }
    }

    /**
     * @param bool|string|mixed $value
     *
     * @return bool|string|null
     */
    private function filterDynamicBooleanFlag($value)
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_numeric($value)) {
            $value = (string)$value;
        }

        if (\is_string($value)) {
            return \trim($value);
        }

        return null;
    }

    /**
     * @param bool|string|mixed $value
     */
    private function resolveDynamicBooleanFlag($value, string $path): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return $value !== '';
        }

        throw new \InvalidArgumentException("Invalid boolean config value for '{$this->getAlias()}.{$path}'.");
    }
}
