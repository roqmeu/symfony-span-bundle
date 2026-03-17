<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerRabbitMqFailCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerRabbitMqOkCommand;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class TracingRabbitMqBundleCest
{
    use CommandCestTrait;

    public function testOk(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:rabbitmq-bundle-ok', true, MessengerRabbitMqOkCommand::class);

        // Ожидаем 2 Span: cli команда продюсера, продюсер
        $this->assertEventsCounts($I, $allEvents, 2, 1);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_PRODUCER);
        $I->assertNotNull($span, 'Проверка наличия спана продюсера RabbitMqBundle');

        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'Проверка подтипа спана продюсера RabbitMqBundle');
        $I->assertEquals(true, $span->isSuccessful(), 'Проверка успешного завершения спана продюсера RabbitMqBundle');
        $I->assertNull($span->getError(), 'Проверка отсутствия ошибки у спана продюсера RabbitMqBundle');

        $I->assertEquals('rabbitmq', $span->context->server['host'] ?? null, 'Проверка host сервера RabbitMqBundle для спана продюсера');
        $I->assertEquals(5672, $span->context->server['port'] ?? null, 'Проверка port сервера RabbitMqBundle для спана продюсера');

        $I->assertEmpty($span->context->message['consumer_name'] ?? null, 'Проверка отсутствия consumer_name у сообщения продюсера RabbitMqBundle');
        $I->assertEmpty($span->context->message['name'] ?? null, 'Проверка отсутствия имени сообщения продюсера RabbitMqBundle');
        $I->assertEquals('rabbitmq_bundle_exchange_ok', $span->context->message['queue_name'] ?? null, 'Проверка имени exchange у сообщения продюсера RabbitMqBundle');

        $startedSpans = [];
        $endedSpans = [];
        $startedTraces = [];
        $endedTraces = [];

        $this->assertCommand($I, $allEvents, 'rabbitmq:consumer consumer_ok -m 1', true);

        // Ожидаем 2 Span: cli команда консюмера, консюмер
        // И 2 Trace: отдельный trace у консюмера (startSpanWithTrace) + trace команды
        $this->assertEventsCounts($I, $allEvents, 2, 2);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSUMER);
        $I->assertNotNull($span, 'Проверка наличия спана консюмера RabbitMqBundle');

        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'Проверка подтипа спана консюмера RabbitMqBundle');

        $I->assertEquals(true, $span->isSuccessful(), 'Проверка успешного завершения спана консюмера RabbitMqBundle');
        $I->assertNull($span->getError(), 'Проверка отсутствия ошибки у спана консюмера RabbitMqBundle');

        $I->assertEquals('rabbitmq', $span->context->server['host'] ?? null, 'Проверка host сервера RabbitMqBundle для спана консюмера');
        $I->assertEquals(5672, $span->context->server['port'] ?? null, 'Проверка port сервера RabbitMqBundle для спана консюмера.');

        $I->assertNotEmpty($span->context->message['consumer_name'] ?? null, 'Проверка наличия consumer_name у сообщения консюмера RabbitMqBundle.');
        $I->assertEmpty($span->context->message['name'] ?? null, 'Проверка отсутствия имени сообщения консюмера RabbitMqBundle.');
        $I->assertEquals('rabbitmq_bundle_queue_ok', $span->context->message['queue_name'] ?? null, 'Проверка имени очереди у сообщения консюмера RabbitMqBundle.');
    }

    public function testError(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:rabbitmq-bundle-fail', true, MessengerRabbitMqFailCommand::class);

        // Ожидаем 2 Span: cli команда продюсера, продюсер
        $this->assertEventsCounts($I, $allEvents, 2, 1);

        $startedSpans = [];
        $endedSpans = [];
        $startedTraces = [];
        $endedTraces = [];

        $this->assertCommand($I, $allEvents, 'rabbitmq:consumer consumer_fail -m 1', false);

        // Ожидаем 2 Span: cli команда консюмера, консюмер
        // И 2 Trace: отдельный trace у консюмера (startSpanWithTrace) + trace команды
        $this->assertEventsCounts($I, $allEvents, 2, 2);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSUMER);
        $I->assertNotNull($span, 'Проверка наличия спана консюмера RabbitMqBundle.');

        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'Проверка подтипа спана консюмера RabbitMqBundle.');

        $I->assertEquals(false, $span->isSuccessful(), 'Проверка неуспешного завершения спана консюмера RabbitMqBundle.');
        $I->assertInstanceOf(\RuntimeException::class, $span->getError(), 'Проверка типа ошибки у спана консюмера RabbitMqBundle.');

        $I->assertEquals('rabbitmq', $span->context->server['host'] ?? null, 'Проверка host сервера RabbitMqBundle для спана консюмера.');
        $I->assertEquals(5672, $span->context->server['port'] ?? null, 'Проверка port сервера RabbitMqBundle для спана консюмера.');

        $I->assertNotEmpty($span->context->message['consumer_name'] ?? null, 'Проверка наличия consumer_name у сообщения консюмера RabbitMqBundle.');
        $I->assertEmpty($span->context->message['name'] ?? null, 'Проверка отсутствия имени сообщения консюмера RabbitMqBundle.');
        $I->assertEquals('rabbitmq_bundle_queue_fail', $span->context->message['queue_name'] ?? null, 'Проверка имени очереди у сообщения консюмера RabbitMqBundle при ошибке.');
    }
}
