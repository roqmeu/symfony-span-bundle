<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use OldSound\RabbitMqBundle\OldSoundRabbitMqBundle;
use OldSound\RabbitMqBundle\RabbitMq\AMQPConnectionFactory;
use OldSound\RabbitMqBundle\RabbitMq\AnonConsumer;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumer;
use OldSound\RabbitMqBundle\RabbitMq\Consumer;
use OldSound\RabbitMqBundle\RabbitMq\DynamicConsumer;
use OldSound\RabbitMqBundle\RabbitMq\MultipleConsumer;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Roqmeu\SpanBundle\DependencyInjection\SpanExtension;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\Tracing\RabbitMqBundle\RabbitMqConnectionMetadataRegistry;
use Roqmeu\SpanBundle\Tracing\RabbitMqBundle\TracingAmqpConnectionFactory;
use Roqmeu\SpanBundle\Tracing\RabbitMqBundle\TracingAnonConsumer;
use Roqmeu\SpanBundle\Tracing\RabbitMqBundle\TracingBatchConsumer;
use Roqmeu\SpanBundle\Tracing\RabbitMqBundle\TracingConsumer;
use Roqmeu\SpanBundle\Tracing\RabbitMqBundle\TracingDynamicConsumer;
use Roqmeu\SpanBundle\Tracing\RabbitMqBundle\TracingMultipleConsumer;
use Roqmeu\SpanBundle\Tracing\RabbitMqBundle\TracingProducer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RabbitMqBundleMiddlewarePass implements CompilerPassInterface
{
    private const CONNECTION_FACTORY_PREFIX = 'old_sound_rabbit_mq.connection_factory.';

    private const TAGS = [
        'old_sound_rabbit_mq.producer',
        'old_sound_rabbit_mq.consumer',
        'old_sound_rabbit_mq.multi_consumer',
        'old_sound_rabbit_mq.dynamic_consumer',
        'old_sound_rabbit_mq.anon_consumer',
        'old_sound_rabbit_mq.batch_consumer',
    ];

    public function process(ContainerBuilder $container): void
    {
        $config = SpanExtension::getConfig($container);

        if ($config === null || $config['enabled'] !== true || $config['tracing_enabled'] !== true) {
            return;
        }

        if (!\class_exists(OldSoundRabbitMqBundle::class)) {
            return;
        }

        if (!$container->hasDefinition(RabbitMqConnectionMetadataRegistry::class)) {
            $container->register(RabbitMqConnectionMetadataRegistry::class, RabbitMqConnectionMetadataRegistry::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
        }

        $this->decorateConnectionFactories($container);
        $this->instrumentRabbitMqServices($container);
    }

    private function decorateConnectionFactories(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!\str_starts_with($id, self::CONNECTION_FACTORY_PREFIX)) {
                continue;
            }

            $connectionName = \substr($id, \strlen(self::CONNECTION_FACTORY_PREFIX));
            if ($connectionName === '') {
                continue;
            }

            $class = $this->resolveDefinitionClass($container, $definition);
            if ($class === null || !\is_a($class, AMQPConnectionFactory::class, true)) {
                continue;
            }

            $definition->setClass(TracingAmqpConnectionFactory::class);

            $definition->addMethodCall('registerConnectionMetadata', [$connectionName, new Reference(RabbitMqConnectionMetadataRegistry::class)]);
        }
    }

    private function instrumentRabbitMqServices(ContainerBuilder $container): void
    {
        $serviceIds = $this->collectServiceIds($container);

        foreach ($serviceIds as $id) {
            if (!$container->hasDefinition($id)) {
                continue;
            }

            $definition = $container->getDefinition($id);
            if ($definition->isAbstract() || $definition->getDecoratedService() !== null) {
                continue;
            }

            $class = $this->resolveDefinitionClass($container, $definition);
            if ($class === null) {
                continue;
            }

            $tracingClass = $this->resolveTracingClass($class);
            if ($tracingClass === null) {
                continue;
            }

            $definition->setClass($tracingClass);
            $definition->addMethodCall('setSpanTracer', [new Reference(SpanTracer::class)]);
            $definition->addMethodCall('spanBundleSetRegistry', [new Reference(RabbitMqConnectionMetadataRegistry::class)]);

            $connectionName = $this->resolveConnectionName($definition);

            if ($connectionName !== null) {
                $definition->addMethodCall('spanBundleSetConnectionName', [$connectionName]);
            }
        }
    }

    private function collectServiceIds(ContainerBuilder $container): array
    {
        $ids = [];

        foreach (self::TAGS as $tag) {
            $ids[] = \array_keys($container->findTaggedServiceIds($tag));
        }

        return \array_values(\array_unique(\array_merge(...$ids)));
    }

    private function resolveDefinitionClass(ContainerBuilder $container, Definition $definition): ?string
    {
        $class = $definition->getClass();
        if ($class === null) {
            return null;
        }

        $class = $container->getParameterBag()->resolveValue($class);
        if (!\is_string($class)) {
            return null;
        }

        return $class;
    }

    private function resolveTracingClass(string $class): ?string
    {
        if (\is_a($class, Producer::class, true)) {
            return TracingProducer::class;
        }

        if (\is_a($class, BatchConsumer::class, true)) {
            return TracingBatchConsumer::class;
        }

        if (\is_a($class, MultipleConsumer::class, true)) {
            return TracingMultipleConsumer::class;
        }

        if (\is_a($class, DynamicConsumer::class, true)) {
            return TracingDynamicConsumer::class;
        }

        if (\is_a($class, AnonConsumer::class, true)) {
            return TracingAnonConsumer::class;
        }

        if (\is_a($class, Consumer::class, true)) {
            return TracingConsumer::class;
        }

        return null;
    }

    private function resolveConnectionName(Definition $definition): ?string
    {
        $arguments = $definition->getArguments();
        if (!isset($arguments[0])) {
            return null;
        }

        $argument = $arguments[0];
        if (!$argument instanceof Reference) {
            return null;
        }

        $referenceId = (string)$argument;
        if (!\str_starts_with($referenceId, 'old_sound_rabbit_mq.connection.')) {
            return null;
        }

        $connectionName = \substr($referenceId, \strlen('old_sound_rabbit_mq.connection.'));

        return $connectionName !== '' ? $connectionName : null;
    }
}
