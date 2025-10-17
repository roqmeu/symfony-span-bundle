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

class MessengerOnRedisOkCest
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
            new StringInput('app:test:messenger-redis-ok'),
            new BufferedOutput()
        );
        $I->assertEquals(0, $exitCode, 'Ожидали нулевой код выхода для команды');

        $exitCode = $application->run(
            new StringInput('messenger:consume transport-redis --no-reset --limit=1'),
            new BufferedOutput()
        );
        $I->assertEquals(0, $exitCode, 'Ожидали нулевой код выхода для команды');

        // Ожидаем минимум 4 Span: команда продюсера, спан продюсера и команда консюмера и спан консюмера
        $I->assertCount(4, $startedSpans, 'Ожидали 4 события SpanStartedEvent');
        $I->assertCount(4, $finishedSpans, 'Ожидали 4 события SpanFinishedEvent');

        // Ожидаем минимум 3 Trace: команда продюсера, команда консюмера и спан консюмера
        $I->assertCount(3, $startedTraces, 'Ожидали 3 события TraceStartedEvent');
        $I->assertCount(3, $finishedTraces, 'Ожидали 3 события TraceFinishedEvent');

        // Находим спан консюмера среди завершенных спанов
        $consumerSpan = null;
        foreach ($finishedSpans as $span) {
            if ($span->span->type === SpanBundle::TRANSACTION_TYPE_CONSUMER) {
                $consumerSpan = $span;
                break;
            }
        }

        $I->assertNotNull($consumerSpan, 'Должен быть спан консюмера');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_REDIS, $consumerSpan->span->subtype, 'Подтип спана должен быть задан');

        $I->assertEquals(true, $consumerSpan->span->successful, 'Спан должен быть успешным');
        $I->assertEquals(null, $consumerSpan->span->error, 'Ошибка должна отсутствовать');

        $I->assertEquals(true, $spanPool->empty(), 'Ожидали пустой SpanPool');
        $I->assertEquals(true, $tracePool->empty(), 'Ожидали пустой TracePool');
    }
}


