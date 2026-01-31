<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;

class TracingMessengerAmqpCest
{
    use CommandCestTrait;

    public function testErrorMessengerRabbitmq(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:messenger-rabbitmq-fail', true);

        // Ожидаем 2 Span: cli команда продюсера, продюсер
        $this->assertEventsCounts($I, $allEvents, 2, 1);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_PRODUCER);

        $I->assertNotNull($span, 'TODO');
        $I->assertEquals('PRODUCE to rabbitmq_exchange_name', $span->getName(), 'TODO');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'TODO');

        $I->assertEquals('rabbitmq', $span->context->server['host'], 'TODO');
        $I->assertEquals(5672, $span->context->server['port'], 'TODO');

        $I->assertEquals('rabbitmq', $span->context->target['type'], 'TODO');
        $I->assertEquals('rabbitmq_exchange_name', $span->context->target['name'], 'TODO');

        $I->assertNotEmpty($span->context->message['name'], 'TODO');
        $I->assertEquals('rabbitmq_exchange_name', $span->context->message['queue_name'], 'TODO');

        $startedSpans = [];
        $endedSpans = [];
        $startedTraces = [];
        $endedTraces = [];

        $this->assertCommand($I, $allEvents, 'messenger:consume transport-rabbitmq --no-reset --limit=3', true);

        // Ожидаем 4 Span: cli команда консюмера, консюмер, 2 повтора
        $this->assertEventsCounts($I, $allEvents, 4, 4);

        $index = 0;

        /** @var SpanStartedEvent $event */
        foreach ($startedSpans as $event) {
            $span = $event->span;

            if ($span->getType() === SpanBundle::SPAN_TYPE_CONSUMER) {
                $I->assertEquals('CONSUME from rabbitmq_queue_name', $span->getName(), 'TODO');
                $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'TODO');

                $I->assertEquals('rabbitmq', $span->context->server['host'], 'TODO');
                $I->assertEquals(5672, $span->context->server['port'], 'TODO');

                $I->assertNotEmpty($span->context->message['consumer_name'], 'TODO');
                $I->assertNotEmpty($span->context->message['name'], 'TODO');
                $I->assertEquals('rabbitmq_queue_name', $span->context->message['queue_name'], 'TODO');

                $attempt = $event->span->context->message['retry_attempt'] ?? null;

                $I->assertEquals($index, $attempt, 'TODO');

                if ($attempt > 0) {
                    $I->assertGreaterThan(0, $event->span->context->message['retry_delay'] ?? null, 'TODO');
                }

                $index++;
            }
        }
    }
}
