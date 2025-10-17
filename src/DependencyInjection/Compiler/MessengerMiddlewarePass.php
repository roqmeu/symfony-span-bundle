<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use Composer\InstalledVersions;
use Roqmeu\SpanBundle\Tracing\Messenger\TracingConsumerMiddleware;
use Roqmeu\SpanBundle\Tracing\Messenger\TracingConsumerMiddlewareV5;
use Roqmeu\SpanBundle\Tracing\Messenger\TracingConsumerMiddlewareV6;
use Roqmeu\SpanBundle\Tracing\Messenger\TracingProducerMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MessengerMiddlewarePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!\interface_exists('Symfony\Component\Messenger\MessageBusInterface')) {
            return;
        }

        $container->register(TracingProducerMiddleware::class, TracingProducerMiddleware::class)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $messengerVersion = InstalledVersions::getVersion('symfony/messenger');
        $consumerMiddleware = $messengerVersion !== null && \version_compare($messengerVersion, '6.0', '>=') === true
            ? TracingConsumerMiddlewareV6::class
            : TracingConsumerMiddlewareV5::class;

        $container->register($consumerMiddleware, $consumerMiddleware)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $container->setAlias(TracingConsumerMiddleware::class, $consumerMiddleware);

        $busServiceIds = \array_keys($container->findTaggedServiceIds('messenger.bus'));
        foreach ($busServiceIds as $busId) {
            $param = $busId . '.middleware';
            if (!$container->hasParameter($param)) {
                continue;
            }

            $items = $container->getParameter($param);
            if (!\is_array($items)) {
                continue;
            }

            $filteredItems = [];
            foreach ($items as $item) {
                $id = \is_string($item) ? $item : ($item['id'] ?? null);

                if ($id === TracingProducerMiddleware::class || $id === TracingConsumerMiddleware::class) {
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
                \array_splice($items, $handleIdx, 0, [['id' => TracingConsumerMiddleware::class]]);
            }

            $container->setParameter($param, $items);
        }
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
}
