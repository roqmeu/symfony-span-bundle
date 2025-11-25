<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\DependencyInjection\Compiler;

use Roqmeu\SpanBundle\DependencyInjection\SpanExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CleanupPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter(SpanExtension::CONFIG_PARAMETER)) {
            $container->getParameterBag()->remove(SpanExtension::CONFIG_PARAMETER);
        }
    }
}
