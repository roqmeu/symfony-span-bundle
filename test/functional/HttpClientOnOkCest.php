<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class HttpClientOnOkCest
{
    use CommandCestTrait;

    public function testOkHttpClient(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:http-client-ok', true);

        // Ожидаем 2 спана: один для команды и один для Symfony HTTP запроса
        $this->assertEventsCounts($I, $allEvents, 2, 1);

        $span = null;

        foreach ($startedSpans as $event) {
            if ($event->span->getType() === SpanBundle::SPAN_TYPE_CLIENT) {
                $span = $event->span;

                break;
            }
        }

        $I->assertNotNull($span, 'Ожидали найти спан для Symfony HTTP запроса');
        $I->assertEquals(SpanBundle::SPAN_TYPE_CLIENT, $span->getType(), 'Ожидали тип client');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_HTTP, $span->getSubtype(), 'Ожидали подтип http');
    }
}
