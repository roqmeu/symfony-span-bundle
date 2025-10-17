<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\SpanPool;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;
use Roqmeu\SpanBundle\Transport\Event\SpanFinishedEvent;
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceFinishedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceStartedEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Kernel;

class CommandOnOkCest
{
    public function dispatchEvent(FunctionalTester $I): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $I->grabService('event_dispatcher');

        /** @var SpanPool $spanPool */
        $spanPool = $I->grabService(SpanPool::class);
        /** @var TransactionPool $tracePool */
        $tracePool = $I->grabService(TransactionPool::class);

        $application = $I->getApplication();

        $startedSpans = [];
        $dispatcher->addListener(SpanStartedEvent::class, static function ($event) use (&$startedSpans): void {
            $startedSpans[] = $event;
        });
        $finishedSpans = [];
        $dispatcher->addListener(SpanFinishedEvent::class, static function ($event) use (&$finishedSpans): void {
            $finishedSpans[] = $event;
        });
        $startedTraces = [];
        $dispatcher->addListener(TraceStartedEvent::class, static function ($event) use (&$startedTraces): void {
            $startedTraces[] = $event;
        });
        $finishedTraces = [];
        $dispatcher->addListener(TraceFinishedEvent::class, static function ($event) use (&$finishedTraces): void {
            $finishedTraces[] = $event;
        });

        $exitCode = $application->run(
            new StringInput('app:test:command-ok'),
            new BufferedOutput()
        );
        $I->assertEquals(0, $exitCode, 'Ожидали нулевой код выхода для команды');

        $I->assertCount(1, $startedSpans, 'Ожидали одно событие SpanStartedEvent');
        $I->assertCount(1, $startedTraces, 'Ожидали одно событие TraceStartedEvent');
        $I->assertInstanceOf(SpanStartedEvent::class, $startedSpans[0], 'Ожидали событие типа SpanStartedEvent');
        $I->assertInstanceOf(TraceStartedEvent::class, $startedTraces[0], 'Ожидали событие типа TraceStartedEvent');

        $I->assertCount(1, $finishedSpans, 'Ожидали одно событие SpanFinishedEvent');
        $I->assertCount(1, $finishedTraces, 'Ожидали одно событие TraceFinishedEvent');
        $I->assertInstanceOf(SpanFinishedEvent::class, $finishedSpans[0], 'Ожидали событие типа SpanFinishedEvent');
        $I->assertInstanceOf(TraceFinishedEvent::class, $finishedTraces[0], 'Ожидали событие типа TraceFinishedEvent');

        $I->assertEquals($startedSpans[0]->span, $finishedSpans[0]->span, 'TODO');
        $I->assertEquals($startedTraces[0]->trace, $finishedTraces[0]->trace, 'TODO');

        $I->assertEquals(true, $spanPool->empty(), 'TODO');
        $I->assertEquals(true, $tracePool->empty(), 'TODO');

        /** @var SpanFinishedEvent $event */
        $event = $finishedSpans[0];
        $I->assertEquals('app:test:command-ok', $event->span->name, 'Ожидали имя команды');
        $I->assertEquals(SpanBundle::TRANSACTION_TYPE_CONSOLE, $event->span->type, 'Ожидали тип команды');
        $I->assertEquals(true, $event->span->successful, 'TODO');
        $I->assertEquals(null, $event->span->error, 'TODO');

        $I->assertEqualsCanonicalizing(
            $event->span->context->command,
            ['name' => 'app:test:command-ok'],
            'TODO'
        );
        $I->assertEqualsCanonicalizing(
            $event->span->context->framework,
            [
                'debug' => false,
                'environment' => 'test',
                'framework' => 'symfony',
                'version' => Kernel::VERSION
            ],
            'TODO'
        );
        $I->assertEqualsCanonicalizing(
            $event->span->context->process,
            [
                'executable' => '/usr/local/bin/php',
                'interactive' => \posix_isatty(\STDIN) || \posix_isatty(\STDOUT),
                'pid' => \posix_getpid(),
                'parent_pid' => \posix_getppid(),
                'runtime_name' => 'cli',
                'runtime_version' => PHP_VERSION
            ],
            'TODO'
        );
    }
}
