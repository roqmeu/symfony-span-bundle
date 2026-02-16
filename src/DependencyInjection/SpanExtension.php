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
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceEndedEvent;
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

        $dynamicFlag = $this->filterDynamicBooleanFlag($config['profiling']['enabled'] ?? null);
        $container->setParameter('span.profiling_enabled', $dynamicFlag);
        $buildConfig['profiling_enabled'] = $this->resolveDynamicBooleanFlag($dynamicFlag, 'profiling.enabled');

        $buildConfig['profiling_threshold'] = $config['profiling']['threshold'] ?? 0.0;
        $buildConfig['profiling_allowed_types'] = ($config['profiling']['allowed_types'] ?? null) ?: null;
        $buildConfig['profiling_ignored_types'] = ($config['profiling']['ignored_types'] ?? null) ?: null;
        $buildConfig['profiling_allowed_subtypes'] = ($config['profiling']['allowed_subtypes'] ?? null) ?: null;
        $buildConfig['profiling_ignored_subtypes'] = ($config['profiling']['ignored_subtypes'] ?? null) ?: null;

        if ($buildConfig['enabled'] === true) {
            if (!$container->hasDefinition(SpanInteractor::class)) {
                $container->register(SpanInteractor::class, BundleSpanInteractor::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true);
            }

            if (!$container->hasDefinition(SpanTracer::class)) {
                $container->register(SpanTracer::class, BundleSpanTracer::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true);
            }
        } else {
            if (!$container->hasDefinition(SpanInteractor::class)) {
                $container->register(SpanInteractor::class, NullSpanInteractor::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true);
            }

            if (!$container->hasDefinition(SpanTracer::class)) {
                $container->register(SpanTracer::class, NullSpanTracer::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true);
            }
        }

        if ($buildConfig['enabled'] === true && $buildConfig['tracing_enabled'] === true) {
            $container->register(TracingCommandListener::class, TracingCommandListener::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);

            $container->register(TracingRequestListener::class, TracingRequestListener::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
        }

        $dynamicFlag = $this->filterDynamicBooleanFlag($config['bridge']['elastic_apm']['enabled'] ?? null);
        $container->setParameter('span.bridge_elastic_apm_enabled', $dynamicFlag);
        $buildConfig['bridge_elastic_apm_enabled'] = $this->resolveDynamicBooleanFlag($dynamicFlag, 'elastic_apm.enabled');

        $dynamicFlag = $this->filterDynamicBooleanFlag($config['bridge']['elastic_apm']['use_span_compression'] ?? null);
        $container->setParameter('span.bridge_elastic_apm_use_span_compression', $dynamicFlag);
        $buildConfig['bridge_elastic_apm_use_span_compression'] = $this->resolveDynamicBooleanFlag($dynamicFlag, 'elastic_apm.use_span_compression');

        if ($buildConfig['enabled'] === true) {
            if ($buildConfig['bridge_elastic_apm_enabled'] === true && !$container->hasDefinition(ElasticApmBridge::class)) {
                $container->register(ElasticApmBridge::class, ElasticApmBridge::class)
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                    ->setArgument(0, '%span.bridge_elastic_apm_enabled%')
                    ->setArgument(1, '%span.bridge_elastic_apm_use_span_compression%')
                    ->addTag('kernel.event_listener', ['event' => SpanStartedEvent::class, 'method' => 'onSpanStarted', 'priority' => -256])
                    ->addTag('kernel.event_listener', ['event' => TraceEndedEvent::class, 'method' => 'onTraceEnded', 'priority' => -256]);
            }
        }

        $container->setParameter(self::CONFIG_PARAMETER, $buildConfig);
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
