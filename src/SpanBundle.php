<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle;

use Roqmeu\SpanBundle\DependencyInjection\Compiler\CleanupPass;
use Roqmeu\SpanBundle\DependencyInjection\Compiler\DoctrineMiddlewarePass;
use Roqmeu\SpanBundle\DependencyInjection\Compiler\GuzzleMiddlewarePass;
use Roqmeu\SpanBundle\DependencyInjection\Compiler\ProfilerMiddlewarePass;
use Roqmeu\SpanBundle\DependencyInjection\Compiler\RabbitMqBundleMiddlewarePass;
use Roqmeu\SpanBundle\DependencyInjection\Compiler\SymfonyHttpClientPass;
use Roqmeu\SpanBundle\DependencyInjection\Compiler\SymfonyMessengerMiddlewarePass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SpanBundle extends Bundle
{
    public const UNKNOWN = 'unknown';

    # Span core types
    public const SPAN_TYPE_CONSOLE = 'console';
    public const SPAN_TYPE_CONSUMER = 'consumer';
    public const SPAN_TYPE_SERVER = 'server';

    # Span types
    public const SPAN_TYPE_CLIENT = 'client';
    public const SPAN_TYPE_DB = 'db';
    public const SPAN_TYPE_INTERNAL = 'internal';
    public const SPAN_TYPE_PRODUCER = 'producer';

    # Span subtypes
    public const SPAN_SUBTYPE_HTTP = 'http';

    public const SPAN_SUBTYPE_RABBITMQ = 'rabbitmq';
    public const SPAN_SUBTYPE_REDIS = 'redis';
    public const SPAN_SUBTYPE_MESSENGER = 'messenger';

    public const SPAN_SUBTYPE_DOCTRINE = 'doctrine';
    public const SPAN_SUBTYPE_MSSQL = 'mssql';
    public const SPAN_SUBTYPE_MYSQL = 'mysql';
    public const SPAN_SUBTYPE_ORACLE = 'oracle';
    public const SPAN_SUBTYPE_POSTGRESQL = 'postgresql';
    public const SPAN_SUBTYPE_SQLITE = 'sqlite';

    public const SPAN_SUBTYPE_APP = 'app';
    public const SPAN_SUBTYPE_PROFILE = 'profile';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DoctrineMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        $container->addCompilerPass(new SymfonyMessengerMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        $container->addCompilerPass(new RabbitMqBundleMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
        $container->addCompilerPass(new ProfilerMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);

        $container->addCompilerPass(new GuzzleMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -100);
        $container->addCompilerPass(new SymfonyHttpClientPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -100);

        $container->addCompilerPass(new CleanupPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -256);
    }
}
