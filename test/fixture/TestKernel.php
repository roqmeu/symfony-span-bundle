<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use GuzzleHttp\Client;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Fixture\Command\CommandFail;
use Roqmeu\SpanBundle\Test\Fixture\Command\CommandOk;
use Roqmeu\SpanBundle\Test\Fixture\Command\DbalOkCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\GuzzleOkCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\HttpClientOkCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerRabbitMqFailCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerRabbitMqOkCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerRedisOkCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerSyncFailCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerSyncOkCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\ProfilerCommand;
use Roqmeu\SpanBundle\Test\Fixture\Messenger\FailEvent;
use Roqmeu\SpanBundle\Test\Fixture\Messenger\FailEventHandler;
use Roqmeu\SpanBundle\Test\Fixture\Messenger\FailEventRabbitMq;
use Roqmeu\SpanBundle\Test\Fixture\Messenger\FailEventSync;
use Roqmeu\SpanBundle\Test\Fixture\Messenger\OkEvent;
use Roqmeu\SpanBundle\Test\Fixture\Messenger\OkEventHandler;
use Roqmeu\SpanBundle\Test\Fixture\Messenger\OkEventRabbitMq;
use Roqmeu\SpanBundle\Test\Fixture\Messenger\OkEventRedis;
use Roqmeu\SpanBundle\Test\Fixture\Messenger\OkEventSync;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new SpanBundle();
    }

    // Symfony 5.4 compatibility: signature may be (ContainerConfigurator $c, LoaderInterface $loader)
    // Symfony 6.*/7.*: (ContainerConfigurator $c, LoaderInterface $loader, ContainerBuilder $builder)
    private function configureContainer(
        ContainerConfigurator $c,
        LoaderInterface $loader,
        ContainerBuilder $builder = null
    ): void {
        $parameters = $c->parameters();

        $parameters->set('container.dumper.inline_factories', true);
        $parameters->set('.container.dumper.inline_factories', true);

        $c->extension('framework', [
            'secret' => 'SECRET',
            'test' => true,
            'messenger' => [
                'enabled' => true,
                'transports' => [
                    'transport-sync' => ['dsn' => 'sync://'],
                    'transport-rabbitmq' => [
                        'dsn' => 'amqp://rabbitmq:rabbitmq@rabbitmq:5672/',
                        'options' => [
                            'queues' => [
                                'rabbitmq_queue_name' => [],
                            ],
                            'exchange' => [
                                'name' => 'rabbitmq_exchange_name'
                            ]
                        ],
                        'retry_strategy' => [
                            'max_retries' => 2,
                            'delay' => 1000,
                            'multiplier' => 2,
                        ],
                    ],
                    'transport-redis' => [
                        'dsn' => 'redis://redis:6379',
                        'options' => [
                            'lazy' => true,
                        ]
                    ],
                ],
                'routing' => [
                    OkEventSync::class => 'transport-sync',
                    FailEventSync::class => 'transport-sync',
                    OkEventRabbitMq::class => 'transport-rabbitmq',
                    FailEventRabbitMq::class => 'transport-rabbitmq',
                    OkEventRedis::class => 'transport-redis',
                ],
            ],
            'http_client' => [
                'enabled' => true,
                'scoped_clients' => [
                    'scoped.span.client' => [
                        'base_uri' => 'http://scoped.span-bundle.lan',
                    ],
                ],
            ],
        ]);

        $c->extension('doctrine', [
            'dbal' => [
                'connections' => [
                    'default' => [
                        'url' => 'pgsql://postgres:postgres@pgsql:5432/symfony_span_bundle',
                    ],
                    'secondary' => [
                        'url' => 'pgsql://postgres:postgres@pgsql:5432/symfony_span_bundle',
                    ],
                ],
            ],
        ]);

        $c->extension('span', [
            'enabled' => true,
            'tracing' => [
                'enabled' => true,
            ],
            'profiling' => [
                'enabled' => '%env(bool:default::SPAN_PROFILER_ENABLED)%',
                'threshold' => 0.01,
            ]
        ]);

        $services = $c->services();
        $services->defaults()->autowire()->autoconfigure();

        $services->set('test.guzzle.client', Client::class);

        $services->set(FailEventHandler::class)
            ->tag('messenger.message_handler', ['handles' => FailEvent::class]);
        $services->set(OkEventHandler::class)
            ->tag('messenger.message_handler', ['handles' => OkEvent::class]);

        $services->set(CommandOk::class)
            ->tag('console.command');
        $services->set(CommandFail::class)
            ->tag('console.command');

        $services->set(MessengerSyncOkCommand::class)
            ->tag('console.command');
        $services->set(MessengerSyncFailCommand::class)
            ->tag('console.command');
        $services->set(MessengerRabbitMqOkCommand::class)
            ->tag('console.command');
        $services->set(MessengerRabbitMqFailCommand::class)
            ->tag('console.command');
        $services->set(MessengerRedisOkCommand::class)
            ->tag('console.command');

        $services->set(ProfilerCommand::class)
            ->tag('console.command');

        $services->set(HttpClientOkCommand::class)
            ->tag('console.command');

        $services->set(GuzzleOkCommand::class)
            ->arg('$client', new ReferenceConfigurator('test.guzzle.client'))
            ->tag('console.command');

        $services->set(DbalOkCommand::class)
            ->arg('$connection', new ReferenceConfigurator('doctrine.dbal.default_connection'))
            ->tag('console.command');
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        // Маршруты для HTTP-цикла нам пока не нужны, но оставим метод пустым.
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        $base = $this->getProjectDir() . '/var/cache/' . $this->environment;
        $suffix = getenv('TEST_TOKEN') ?: (string)getmypid();

        return $base . '/' . $suffix;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log/' . $this->environment;
    }
}
