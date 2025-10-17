<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use Roqmeu\SpanBundle\Tracing\HttpClient\TracingHttpClientV5;
use Roqmeu\SpanBundle\Tracing\HttpClient\TracingHttpClientV6;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyHttpClientPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!\interface_exists('Symfony\Contracts\HttpClient\HttpClientInterface')) {
            return;
        }

        $tracingClass = \version_compare(Kernel::VERSION, '6.0', '>=') === true
            ? TracingHttpClientV6::class
            : TracingHttpClientV5::class;

        $clients = ['http_client'];

        foreach ($container->getDefinitions() as $id => $definition) {
            if (\strpos($id, '.inner') !== false || \strpos($id, '.tracing') !== false) {
                continue;
            }

            if ($definition->isAbstract()) {
                continue;
            }

            if ($definition->getDecoratedService() !== null) {
                continue;
            }

            $class = $definition->getClass();

            if ($class === null) {
                continue;
            }

            while (\is_string($class) && $container->hasParameter($class)) {
                $param = $container->getParameter($class);

                if (!\is_string($param)) {
                    continue 2;
                }

                $class = $param;
            }

            if (!\is_string($class) || !\class_exists($class, false)) {
                continue;
            }

            if (\is_subclass_of($class, HttpClientInterface::class)) {
                $clients[] = $id;
            }
        }

        foreach ($clients as $clientId) {
            $decoratedId = $clientId . '.span.inner';

            $decorator = new Definition($tracingClass);
            $decorator->setAutoconfigured(true);
            $decorator->setAutowired(true);

            $decorator->setDecoratedService($clientId, $decoratedId);
            $decorator->setArgument('$client', new Reference($decoratedId));

            $container->setDefinition($clientId . '.span.tracing', $decorator);
        }
    }
}
