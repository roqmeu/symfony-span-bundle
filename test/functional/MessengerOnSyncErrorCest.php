<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class MessengerOnSyncErrorCest
{
    use CommandCestTrait;

    public function dispatchEvent(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:messenger-sync-fail', false);

        $this->assertEventsCounts($I, $allEvents, 3, 2);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_PRODUCER);

        $I->assertNotNull($span, 'TODO');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_MESSENGER, $span->getSubtype(), 'TODO');

        $I->assertEquals(false, $span->isSuccessful(), 'TODO');
        $I->assertInstanceOf(\RuntimeException::class, $span->getError(), 'TODO');

        $I->assertEquals('messenger', $span->context->target['type'], 'TODO');
        $I->assertEquals('unknown', $span->context->target['name'], 'TODO');

        $I->assertNotEmpty($span->context->message['name'], 'TODO');
        $I->assertEquals('unknown', $span->context->message['queue_name'], 'TODO');

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSUMER);

        $I->assertNotNull($span, 'Должен быть спан консюмера');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_MESSENGER, $span->getSubtype(), 'Подтип спана должен быть задан');

        $I->assertEquals(false, $span->isSuccessful(), 'TODO');
        $I->assertInstanceOf(\RuntimeException::class, $span->getError(), 'TODO');

        $I->assertEquals('messenger', $span->context->target['type'], 'TODO');
        $I->assertEquals('transport-sync', $span->context->target['name'], 'TODO');

        $I->assertNotEmpty($span->context->message['consumer_name'], 'TODO');
        $I->assertNotEmpty($span->context->message['name'], 'TODO');
        $I->assertEquals('transport-sync', $span->context->message['queue_name'], 'TODO');
    }
}
