<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerRabbitMqFailCommand;
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

        $this->assertCommand($I, $allEvents, 'app:test:messenger-rabbitmq-fail', true, MessengerRabbitMqFailCommand::class);

        // Ожидаем 2 Span: cli команда продюсера, продюсер
        $this->assertEventsCounts($I, $allEvents, 2, 1);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_PRODUCER);

        $I->assertNotNull($span, 'Проверка наличия спана продюсера RabbitMQ');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'Проверка подтипа спана продюсера RabbitMQ');

        $I->assertEquals('rabbitmq', $span->context->server['host'] ?? null, 'Проверка host сервера RabbitMQ для спана продюсера');
        $I->assertEquals(5672, $span->context->server['port'] ?? null, 'Проверка port сервера RabbitMQ для спана продюсера');

        $I->assertNotEmpty($span->context->message['name'] ?? null, 'Проверка имени сообщения в спане продюсера RabbitMQ');
        $I->assertEquals('rabbitmq_exchange_name', $span->context->message['queue_name'] ?? null, 'Проверка имени exchange в контексте сообщения продюсера RabbitMQ');

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
                $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'Проверка подтипа спана консюмера RabbitMQ');

                $I->assertEquals('rabbitmq', $span->context->server['host'] ?? null, 'Проверка host сервера RabbitMQ для спана консюмера');
                $I->assertEquals(5672, $span->context->server['port'] ?? null, 'Проверка port сервера RabbitMQ для спана консюмера');

                $I->assertNotEmpty($span->context->message['consumer_name'] ?? null, 'Проверка имени консюмера в контексте сообщения RabbitMQ');
                $I->assertNotEmpty($span->context->message['name'] ?? null, 'Проверка имени сообщения в спане консюмера RabbitMQ');
                $I->assertEquals('rabbitmq_queue_name', $span->context->message['queue_name'] ?? null, 'Проверка имени очереди в контексте сообщения консюмера RabbitMQ');

                $attempt = $event->span->context->message['retry_attempt'] ?? null;

                $I->assertEquals($index, $attempt, 'Проверка номера попытки обработки сообщения RabbitMQ');

                if ($attempt > 0) {
                    $I->assertGreaterThan(0, $event->span->context->message['retry_delay'] ?? null, 'Проверка положительной задержки повторной обработки сообщения RabbitMQ');
                }

                $index++;
            }
        }
    }
}
