<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use Roqmeu\SpanBundle\DependencyInjection\SpanExtension;
use Roqmeu\SpanBundle\Tracing\Doctrine\TracingMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DoctrineMiddlewarePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $config = SpanExtension::getConfig($container);

        if ($config === null || $config['enabled'] !== true || $config['tracing_enabled'] !== true) {
            return;
        }

        if (!\interface_exists('Doctrine\DBAL\Driver\Middleware')) {
            return;
        }

        if (!$container->hasDefinition(TracingMiddleware::class)) {
            $container->register(TracingMiddleware::class, TracingMiddleware::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->addTag('doctrine.middleware');
        }
    }
}
