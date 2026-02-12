<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use Roqmeu\SpanBundle\DependencyInjection\SpanExtension;
use Roqmeu\SpanBundle\Tracing\Doctrine\TracingMiddlewareV3;
use Roqmeu\SpanBundle\Tracing\Doctrine\TracingMiddlewareV4;
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

        $isDbalV4 = \function_exists('enum_exists') && \enum_exists('Doctrine\\DBAL\\ParameterType');

        $tracingMiddlewareClass = $isDbalV4
            ? TracingMiddlewareV4::class
            : TracingMiddlewareV3::class;

        if (!$container->hasDefinition($tracingMiddlewareClass)) {
            $container->register($tracingMiddlewareClass, $tracingMiddlewareClass)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->addTag('doctrine.middleware');
        }
    }
}
