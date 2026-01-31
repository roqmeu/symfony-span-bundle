<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class TracingRabbitMqBundleCest
{
    use CommandCestTrait;

    public function testOk(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:rabbitmq-bundle-ok', true);

        // Ожидаем 2 Span: cli команда продюсера, продюсер
        $this->assertEventsCounts($I, $allEvents, 2, 1);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_PRODUCER);
        $I->assertNotNull($span, 'Должен быть спан продюсера');

        $I->assertEquals('PRODUCE to rabbitmq_bundle_exchange_ok', $span->getName(), 'Имя спана продюсера');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'Подтип спана продюсера');
        $I->assertEquals(true, $span->isSuccessful(), 'Спан продюсера должен быть успешным');
        $I->assertNull($span->getError(), 'Ошибка должна отсутствовать');

        $I->assertEquals('rabbitmq', $span->context->server['host'], 'Host должен быть из connection');
        $I->assertEquals(5672, $span->context->server['port'], 'Port должен быть из connection');

        $I->assertEquals('rabbitmq', $span->context->target['type'], 'Тип target');
        $I->assertEquals('rabbitmq_bundle_exchange_ok', $span->context->target['name'], 'Имя exchange');

        $I->assertNotEmpty($span->context->message['name'], 'Имя сообщения должно быть заполнено');
        $I->assertEquals('rabbitmq_bundle_exchange_ok', $span->context->message['queue_name'], 'queue_name для producer = exchange');

        $startedSpans = [];
        $endedSpans = [];
        $startedTraces = [];
        $endedTraces = [];

        $this->assertCommand($I, $allEvents, 'rabbitmq:consumer consumer_ok -m 1', true);

        // Ожидаем 2 Span: cli команда консюмера, консюмер
        // И 2 Trace: отдельный trace у консюмера (startSpanWithTrace) + trace команды
        $this->assertEventsCounts($I, $allEvents, 2, 2);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSUMER);
        $I->assertNotNull($span, 'Должен быть спан консюмера');

        $I->assertEquals('CONSUME from rabbitmq_bundle_queue_ok', $span->getName(), 'Имя спана консюмера');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'Подтип спана консюмера');

        $I->assertEquals(true, $span->isSuccessful(), 'Спан должен быть успешным');
        $I->assertNull($span->getError(), 'Ошибка должна отсутствовать');

        $I->assertEquals('rabbitmq', $span->context->server['host'], 'Host должен быть из connection');
        $I->assertEquals(5672, $span->context->server['port'], 'Port должен быть из connection');

        $I->assertNotEmpty($span->context->message['consumer_name'], 'consumer_name должен быть заполнен');
        $I->assertNotEmpty($span->context->message['name'], 'name должен быть заполнен');
        $I->assertEquals('rabbitmq_bundle_queue_ok', $span->context->message['queue_name'], 'queue_name для consumer = queue');
    }

    public function testError(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:rabbitmq-bundle-fail', true);

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
        $I->assertNotNull($span, 'Должен быть спан консюмера');

        $I->assertEquals('CONSUME from rabbitmq_bundle_queue_fail', $span->getName(), 'Имя спана консюмера');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_RABBITMQ, $span->getSubtype(), 'Подтип спана консюмера');

        $I->assertEquals(false, $span->isSuccessful(), 'Спан должен быть неуспешным');
        $I->assertInstanceOf(\RuntimeException::class, $span->getError(), 'Ожидаем RuntimeException в error');
    }
}
