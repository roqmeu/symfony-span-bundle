<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use Composer\InstalledVersions;
use Roqmeu\SpanBundle\DependencyInjection\SpanExtension;
use Roqmeu\SpanBundle\Tracing\Messenger\Amqp\AmqpTransportFactoryDecorator;
use Roqmeu\SpanBundle\Tracing\Messenger\Amqp\AmqpTransportMetadataRegistry;
use Roqmeu\SpanBundle\Tracing\Messenger\Doctrine\DoctrineTransportFactoryDecorator;
use Roqmeu\SpanBundle\Tracing\Messenger\Doctrine\DoctrineTransportMetadataRegistry;
use Roqmeu\SpanBundle\Tracing\Messenger\TracingConsumerMiddlewareV5;
use Roqmeu\SpanBundle\Tracing\Messenger\TracingConsumerMiddlewareV6;
use Roqmeu\SpanBundle\Tracing\Messenger\TracingProducerMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SymfonyMessengerMiddlewarePass implements CompilerPassInterface
{
    private const AMQP_TRANSPORT_FACTORY_ID = 'messenger.transport.amqp.factory';

    private const DOCTRINE_TRANSPORT_FACTORY_ID = 'messenger.transport.doctrine.factory';

    public function process(ContainerBuilder $container): void
    {
        $config = SpanExtension::getConfig($container);

        if ($config === null || $config['enabled'] !== true || $config['tracing_enabled'] !== true) {
            return;
        }

        if (!\interface_exists('Symfony\Component\Messenger\MessageBusInterface')) {
            return;
        }

        $container->register(TracingProducerMiddleware::class, TracingProducerMiddleware::class)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $messengerVersion = InstalledVersions::getVersion('symfony/messenger');
        $consumerMiddlewareClass = $messengerVersion !== null && \version_compare($messengerVersion, '6.0', '>=') === true
            ? TracingConsumerMiddlewareV6::class
            : TracingConsumerMiddlewareV5::class;

        $busServiceIds = \array_keys($container->findTaggedServiceIds('messenger.bus'));

        foreach ($busServiceIds as $busId) {
            $param = $busId . '.middleware';
            if (!$container->hasParameter($param)) {
                continue;
            }

            $handlersLocatorId = $busId . '.messenger.handlers_locator';
            $consumerMiddlewareId = $busId . '.span.middleware.tracing_consumer';

            if (!$container->hasDefinition($consumerMiddlewareId)) {
                $container->register($consumerMiddlewareId, $consumerMiddlewareClass)
                    ->setAutowired(true)
                    ->setAutoconfigured(true)
                    ->setArgument('$handlersLocator', new Reference($handlersLocatorId));
            }

            $items = $container->getParameter($param);
            if (!\is_array($items)) {
                continue;
            }

            $filteredItems = [];
            foreach ($items as $item) {
                $id = \is_string($item) ? $item : ($item['id'] ?? null);

                if ($id === TracingProducerMiddleware::class || $id === $consumerMiddlewareId) {
                    continue;
                }

                $filteredItems[] = $item;
            }
            $items = $filteredItems;

            $sendIdx = $this->findIndexById($items, 'send_message');
            if ($sendIdx !== null) {
                \array_splice($items, $sendIdx, 0, [['id' => TracingProducerMiddleware::class]]);
            }

            $handleIdx = $this->findIndexById($items, 'handle_message');
            if ($handleIdx !== null) {
                \array_splice($items, $handleIdx, 0, [['id' => $consumerMiddlewareId]]);
            }

            $container->setParameter($param, $items);
        }

        $this->decorateAmqpTransportFactory($container);

        $this->decorateDoctrineTransportFactory($container);
    }

    private function findIndexById(array $items, string $targetId): ?int
    {
        foreach ($items as $i => $item) {
            $id = \is_string($item) ? $item : ($item['id'] ?? null);

            if ($id === $targetId) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Decorates AmqpTransportFactory to extract AMQP metadata for tracing.
     */
    private function decorateAmqpTransportFactory(ContainerBuilder $container): void
    {
        if (!\class_exists('Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory')) {
            return;
        }

        if (!$container->hasDefinition(self::AMQP_TRANSPORT_FACTORY_ID)) {
            return;
        }

        if (!$container->hasDefinition(AmqpTransportMetadataRegistry::class)) {
            $container->register(AmqpTransportMetadataRegistry::class, AmqpTransportMetadataRegistry::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
        }

        $decoratorId = self::AMQP_TRANSPORT_FACTORY_ID . '.span.decorator';
        $innerId = $decoratorId . '.inner';

        if ($container->hasDefinition($decoratorId)) {
            return;
        }

        $container->register($decoratorId, AmqpTransportFactoryDecorator::class)
            ->setDecoratedService(self::AMQP_TRANSPORT_FACTORY_ID)
            ->setArguments([new Reference($innerId), new Reference(AmqpTransportMetadataRegistry::class)])
            ->setAutowired(true)
            ->setAutoconfigured(true);
    }

    /**
     * Decorates DoctrineTransportFactory to extract database metadata for tracing.
     */
    private function decorateDoctrineTransportFactory(ContainerBuilder $container): void
    {
        if (!\class_exists('Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransportFactory')) {
            return;
        }

        if (!$container->hasDefinition(self::DOCTRINE_TRANSPORT_FACTORY_ID)) {
            return;
        }

        if (!$container->hasDefinition(DoctrineTransportMetadataRegistry::class)) {
            $container->register(DoctrineTransportMetadataRegistry::class, DoctrineTransportMetadataRegistry::class)
                ->setAutowired(true)
                ->setAutoconfigured(true);
        }

        $decoratorId = self::DOCTRINE_TRANSPORT_FACTORY_ID . '.span.decorator';
        $innerId = $decoratorId . '.inner';

        if ($container->hasDefinition($decoratorId)) {
            return;
        }

        $container->register($decoratorId, DoctrineTransportFactoryDecorator::class)
            ->setDecoratedService(self::DOCTRINE_TRANSPORT_FACTORY_ID)
            ->setArguments([new Reference($innerId), new Reference(DoctrineTransportMetadataRegistry::class), new Reference('doctrine')])
            ->setAutowired(true)
            ->setAutoconfigured(true);
    }
}
