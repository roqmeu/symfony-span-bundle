<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class MessengerOnRedisOkCest
{
    use CommandCestTrait;

    public function testOkMessengerRedis(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:messenger-redis-ok', true);

        // Ожидаем 2 Span: cli команда продюсера, продюсер
        $this->assertEventsCounts($I, $allEvents, 2, 1);

        $startedSpans = [];
        $endedSpans = [];
        $startedTraces = [];
        $endedTraces = [];

        $this->assertCommand($I, $allEvents, 'messenger:consume transport-redis --no-reset --limit=1', true);

        // Ожидаем 2 Span: cli команда консюмера, консюмер
        $this->assertEventsCounts($I, $allEvents, 2, 2);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSUMER);

        $I->assertNotNull($span, 'Должен быть спан консюмера');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_REDIS, $span->getSubtype(), 'Подтип спана должен быть задан');

        $I->assertEquals(true, $span->isSuccessful(), 'Спан должен быть успешным');
        $I->assertEquals(null, $span->getError(), 'Ошибка должна отсутствовать');
    }
}
