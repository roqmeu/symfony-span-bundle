<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerRedisOkCommand;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class TracingMessengerRedisCest
{
    use CommandCestTrait;

    public function testOk(FunctionalTester $I): void
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

        $this->assertCommand($I, $allEvents, 'messenger:consume transport-redis --no-reset --limit=1', true, MessengerRedisOkCommand::class);

        // Ожидаем 2 Span: cli команда консюмера, консюмер
        $this->assertEventsCounts($I, $allEvents, 2, 2);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSUMER);

        $I->assertNotNull($span, 'Проверка наличия спана консюмера Redis');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_REDIS, $span->getSubtype(), 'Проверка подтипа спана консюмера Redis');

        $I->assertEquals(true, $span->isSuccessful(), 'Проверка успешного завершения спана консюмера Redis');
        $I->assertEquals(null, $span->getError(), 'Проверка отсутствия ошибки у спана консюмера Redis');
    }
}
