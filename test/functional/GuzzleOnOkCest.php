<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\SpanPool;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;
use Roqmeu\SpanBundle\Transport\Event\SpanFinishedEvent;
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GuzzleOnOkCest
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

        $exitCode = $application->run(
            new StringInput('app:test:guzzle-ok'),
            new BufferedOutput()
        );
        $I->assertEquals(0, $exitCode, 'Ожидали нулевой код выхода для команды');

        // Ожидаем минимум 2 спана: один для команды и один для Guzzle HTTP запроса
        $I->assertGreaterThanOrEqual(2, count($startedSpans), 'Ожидали минимум два события SpanStartedEvent');
        $I->assertGreaterThanOrEqual(2, count($finishedSpans), 'Ожидали минимум два события SpanFinishedEvent');

        $span = null;
        foreach ($finishedSpans as $event) {
            if ($event->span->type === SpanBundle::SPAN_TYPE_CLIENT) {
                $span = $event->span;

                break;
            }
        }

        $I->assertNotNull($span, 'Ожидали найти спан для Guzzle HTTP запроса');
        $I->assertEquals(SpanBundle::SPAN_TYPE_CLIENT, $span->type, 'Ожидали тип client');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_HTTP, $span->subtype, 'Ожидали подтип http');

        $I->assertEquals(true, $spanPool->empty(), 'Ожидали пустой SpanPool');
        $I->assertEquals(true, $tracePool->empty(), 'Ожидали пустой TracePool');
    }
}

