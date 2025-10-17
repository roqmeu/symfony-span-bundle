<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\State\SpanPool;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;
use Roqmeu\SpanBundle\Transport\Event\SpanFinishedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceFinishedEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CommandOnErrorCest
{
    public function dispatchEventOnError(FunctionalTester $I): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $I->grabService('event_dispatcher');

        /** @var SpanPool $spanPool */
        $spanPool = $I->grabService(SpanPool::class);
        /** @var TransactionPool $tracePool */
        $tracePool = $I->grabService(TransactionPool::class);

        $application = $I->getApplication();

        $finishedSpans = [];
        $dispatcher->addListener(SpanFinishedEvent::class, static function ($event) use (&$finishedSpans): void {
            $finishedSpans[] = $event;
        });
        $finishedTraces = [];
        $dispatcher->addListener(TraceFinishedEvent::class, static function ($event) use (&$finishedTraces): void {
            $finishedTraces[] = $event;
        });

        $exitCode = $application->run(
            new StringInput('app:test:command-fail'),
            new BufferedOutput()
        );
        $I->assertNotEquals(0, $exitCode, 'Ожидали ненулевой код выхода для команды');

        $I->assertCount(1, $finishedSpans, 'Ожидали одно событие SpanFinishedEvent');
        $I->assertCount(1, $finishedTraces, 'Ожидали одно событие TraceFinishedEvent');

        /** @var SpanFinishedEvent $event */
        $event = $finishedSpans[0];
        $I->assertInstanceOf(SpanFinishedEvent::class, $event, 'Ожидали событие типа SpanFinishedEvent');
        $I->assertInstanceOf(\RuntimeException::class, $event->span->error, 'Ожидали RuntimeException');
        $I->assertEquals(false, $event->span->successful, 'TODO');

        $I->assertEquals(true, $spanPool->empty(), 'Ожидали пустой SpanPool');
        $I->assertEquals(true, $tracePool->empty(), 'Ожидали пустой TracePool');
    }
}


