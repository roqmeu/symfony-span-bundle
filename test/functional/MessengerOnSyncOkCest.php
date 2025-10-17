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

class MessengerOnSyncOkCest
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
            new StringInput('app:test:messenger-sync-ok'),
            new BufferedOutput()
        );
        $I->assertEquals(0, $exitCode, 'Ожидали нулевой код выхода для команды');

        // Ожидаем 3 Span: один для команды, второй для продюсера и третий для консюмера
        $I->assertCount(3, $startedSpans, 'Ожидали два события SpanStartedEvent');
        $I->assertCount(3, $finishedSpans, 'Ожидали два события SpanFinishedEvent');

        // Ожидаем 2 Trace: один для команды, второй для консьюмера
        $I->assertCount(2, $startedTraces, 'Ожидали два события TraceStartedEvent');
        $I->assertCount(2, $finishedTraces, 'Ожидали два события TraceFinishedEvent');

        /** @var SpanFinishedEvent $event */
        $event = $finishedSpans[0] ?? null;
        $I->assertEquals(SpanBundle::TRANSACTION_TYPE_CONSUMER, $event->span->type, 'TODO');
        $I->assertNotEmpty($event->span->subtype, 'Подтип спана должен быть задан');
        $I->assertEquals(true, $event->span->successful, 'Спан должен быть успешным');
        $I->assertEquals(null, $event->span->error, 'Ошибка должна отсутствовать');

        $I->assertEquals(true, $spanPool->empty(), 'Ожидали пустой SpanPool');
        $I->assertEquals(true, $tracePool->empty(), 'Ожидали пустой TracePool');
    }
}


