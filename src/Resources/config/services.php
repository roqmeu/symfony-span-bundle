<?php

declare(strict_types=1);

use Roqmeu\SpanBundle\State\SpanPool;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Tracing\Command\TracingCommandListener;
use Roqmeu\SpanBundle\Tracing\Controller\TracingRequestListener;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;
use Roqmeu\SpanBundle\Transport\Dispatcher\SymfonyDispatcher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->defaults()->autowire()->autoconfigure();

    $services->set(SpanPool::class);
    $services->set(TransactionPool::class);

    $services->set(SymfonyDispatcher::class);
    $services->alias(Dispatcher::class, SymfonyDispatcher::class);

    // Listeners
    $services->set(TracingCommandListener::class);
    $services->set(TracingRequestListener::class);
};
