<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use Roqmeu\SpanBundle\DependencyInjection\SpanExtension;
use Roqmeu\SpanBundle\Tracing\HttpClient\TracingHttpClientV5;
use Roqmeu\SpanBundle\Tracing\HttpClient\TracingHttpClientV6;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyHttpClientPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $config = SpanExtension::getConfig($container);

        if ($config === null || $config['enabled'] !== true || $config['tracing_enabled'] !== true) {
            return;
        }

        if (!\interface_exists('Symfony\Contracts\HttpClient\HttpClientInterface')) {
            return;
        }

        $tracingClass = \version_compare(Kernel::VERSION, '6.0', '>=') === true
            ? TracingHttpClientV6::class
            : TracingHttpClientV5::class;

        $clients = \array_keys($container->findTaggedServiceIds('http_client.client'));

        if ($container->has('http_client.transport')) {
            $clients[] = 'http_client.transport';
        }
        if ($container->has('http_client')) {
            $clients[] = 'http_client';
        }

        $clientIds = [];

        foreach (\array_unique($clients) as $id) {
            $this->addHttpClientId($container, $id, $clientIds);
        }

        foreach ($clientIds as $clientId) {
            $decoratorId = $clientId . '.span.tracing.decorator';
            $innerId = $clientId . '.span.tracing.inner';

            if ($container->hasDefinition($decoratorId) || $container->hasDefinition($innerId)) {
                continue;
            }

            $decorator = new Definition($tracingClass);
            $decorator->setAutoconfigured(true);
            $decorator->setAutowired(true);

            $decorator->setDecoratedService($clientId, $innerId);
            $decorator->setArgument('$client', new Reference($innerId));

            $container->setDefinition($decoratorId, $decorator);
        }
    }

    private function addHttpClientId(ContainerBuilder $container, string $id, array &$clients): bool
    {
        if (\in_array($id, $clients, true) || \strpos($id, '.span.tracing.') !== false) {
            return true;
        }

        if (!$container->hasDefinition($id)) {
            return false;
        }

        $definition = $container->getDefinition($id);

        if ($definition->isAbstract() || $definition->getDecoratedService() !== null) {
            return false;
        }

        $class = $definition->getClass();

        while (\is_string($class) && $container->hasParameter($class)) {
            $param = $container->getParameter($class);

            if (!\is_string($param)) {
                $class = null;
                break;
            }

            $class = $param;
        }

        if (!\is_string($class)) {
            return false;
        }

        if ($class !== HttpClientInterface::class && !\is_subclass_of($class, HttpClientInterface::class, true)) {
            return false;
        }

        if (\is_a($class, ScopingHttpClient::class, true)) {
            $baseId = (string)($definition->getArguments()[0] ?? '');

            return $baseId !== '' && $this->addHttpClientId($container, $baseId, $clients);
        }

        $found = false;

        foreach ($definition->getArguments() as $argument) {
            if (\is_array($argument) && \count($argument) === 1) {
                $argument = \current($argument);
            }

            if ($argument instanceof Reference) {
                $found = $found || $this->addHttpClientId($container, (string)$argument, $clients);
            }
        }

        if ($found) {
            return true;
        }

        $clients[] = $id;

        return true;
    }
}
