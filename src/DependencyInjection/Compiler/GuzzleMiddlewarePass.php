<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Roqmeu\SpanBundle\DependencyInjection\SpanExtension;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\Tracing\GuzzleHttp\TracingMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class GuzzleMiddlewarePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $config = SpanExtension::getConfig($container);

        if ($config === null || $config['enabled'] !== true || $config['tracing_enabled'] !== true) {
            return;
        }

        if (!\interface_exists('GuzzleHttp\ClientInterface')) {
            return;
        }

        foreach ($this->findClients($container) as $clientId) {
            $this->addTracingMiddleware($container, $clientId);
        }
    }

    private function findClients(ContainerBuilder $container): array
    {
        $clients = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$this->isGuzzleClient($container, $definition, $id)) {
                continue;
            }

            $clients[] = $id;
        }

        return $clients;
    }

    private function isGuzzleClient(ContainerBuilder $container, Definition $definition, string $id): bool
    {
        if (\strpos($id, '.inner') !== false || \strpos($id, '.tracing') !== false) {
            return false;
        }

        if ($definition->isAbstract() || $definition->getDecoratedService() !== null) {
            return false;
        }

        $class = $definition->getClass();

        if ($class === null) {
            return false;
        }

        while ($container->hasParameter($class)) {
            $param = $container->getParameter($class);

            if (!\is_string($param)) {
                return false;
            }

            $class = $param;
        }

        if (!\class_exists($class, false)) {
            return false;
        }

        return $class === Client::class || \is_subclass_of($class, Client::class);
    }

    private function addTracingMiddleware(ContainerBuilder $container, string $clientId): void
    {
        $client = $container->getDefinition($clientId);
        $args = $client->getArguments();

        $configKey = 0;
        $config = $args[$configKey] ?? null;

        if (!\is_array($config) || $config === []) {
            $configKey = '$config';
            $config = $args[$configKey] ?? null;

            if (!\is_array($config)) {
                $configKey = 0;
                $config = [];
            }
        }

        if (isset($config['handler'])) {
            $this->addMiddlewareToExistingHandler($container, $config['handler']);
        } else {
            $this->createHandlerStack($client, $config, $configKey);
        }
    }

    /**
     * @param Definition|Reference|mixed $handler
     */
    private function addMiddlewareToExistingHandler(ContainerBuilder $container, $handler): void
    {
        $handlerDefinition = $this->resolveHandlerDefinition($container, $handler);

        if ($handlerDefinition === null) {
            return;
        }

        $handlerDefinition->addMethodCall('push', [$this->createMiddlewareFactory(), 'span.tracing']);
    }

    /**
     * @param Definition|Reference|mixed $handler
     */
    private function resolveHandlerDefinition(ContainerBuilder $container, $handler): ?Definition
    {
        if ($handler instanceof Definition) {
            return $handler;
        }

        if ($handler instanceof Reference && $container->hasDefinition((string)$handler)) {
            return $container->getDefinition((string)$handler);
        }

        return null;
    }

    /**
     * @param int|string $configKey
     */
    private function createHandlerStack(Definition $client, array $config, $configKey): void
    {
        $handlerStack = new Definition(HandlerStack::class);
        $handlerStack->setFactory([HandlerStack::class, 'create']);

        $handlerStack->addMethodCall('push', [$this->createMiddlewareFactory(), 'span.tracing']);

        $config['handler'] = $handlerStack;

        $client->setArgument($configKey, $config);
    }

    private function createMiddlewareFactory(): Definition
    {
        $factory = new Definition('callable');
        $factory->setFactory([TracingMiddleware::class, 'create']);

        $factory->setArguments([new Reference(SpanTracer::class)]);

        return $factory;
    }
}
