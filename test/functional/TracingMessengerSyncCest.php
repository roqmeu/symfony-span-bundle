<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerSyncFailCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\MessengerSyncOkCommand;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class TracingMessengerSyncCest
{
    use CommandCestTrait;

    public function testOk(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:messenger-sync-ok', true, MessengerSyncOkCommand::class);

        $this->assertEventsCounts($I, $allEvents, 3, 2);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_PRODUCER);

        $I->assertNotNull($span, 'Проверка наличия спана продюсера Messenger');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_MESSENGER, $span->getSubtype(), 'Проверка подтипа спана продюсера Messenger');

        $I->assertNotEmpty($span->context->message['name'] ?? null, 'Проверка имени сообщения продюсера Messenger');
        $I->assertEquals('transport-sync', $span->context->message['queue_name'] ?? null, 'Проверка имени очереди продюсера Messenger');

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSUMER);

        $I->assertNotNull($span, 'Проверка наличия спана консюмера Messenger');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_MESSENGER, $span->getSubtype(), 'Проверка подтипа спана консюмера Messenger');

        $I->assertEquals(true, $span->isSuccessful(), 'Проверка успешного завершения спана консюмера Messenger');
        $I->assertNull($span->getError(), 'Проверка отсутствия ошибки у спана консюмера Messenger');

        $I->assertNotEmpty($span->context->message['consumer_name'] ?? null, 'Проверка имени консюмера в контексте сообщения Messenger');
        $I->assertNotEmpty($span->context->message['name'] ?? null, 'Проверка имени сообщения консюмера Messenger');
        $I->assertEquals('transport-sync', $span->context->message['queue_name'] ?? null, 'Проверка имени очереди консюмера Messenger');
    }

    public function testError(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:messenger-sync-fail', false, MessengerSyncFailCommand::class);

        $this->assertEventsCounts($I, $allEvents, 3, 2);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_PRODUCER);

        $I->assertNotNull($span, 'Проверка наличия спана продюсера Messenger');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_MESSENGER, $span->getSubtype(), 'Проверка подтипа спана продюсера Messenger');

        $I->assertEquals(false, $span->isSuccessful(), 'Проверка неуспешного завершения спана продюсера Messenger');
        $I->assertInstanceOf(\RuntimeException::class, $span->getError(), 'Проверка типа ошибки у спана продюсера Messenger');

        $I->assertNotEmpty($span->context->message['name'] ?? null, 'Проверка имени сообщения продюсера Messenger');
        $I->assertEquals('unknown', $span->context->message['queue_name'] ?? null, 'Проверка имени очереди продюсера Messenger при ошибке');

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSUMER);

        $I->assertNotNull($span, 'Проверка наличия спана консюмера Messenger');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_MESSENGER, $span->getSubtype(), 'Проверка подтипа спана консюмера Messenger');

        $I->assertEquals(false, $span->isSuccessful(), 'Проверка неуспешного завершения спана консюмера Messenger');
        $I->assertInstanceOf(\RuntimeException::class, $span->getError(), 'Проверка типа ошибки у спана консюмера Messenger');

        $I->assertNotEmpty($span->context->message['name'] ?? null, 'Проверка имени сообщения консюмера Messenger');
        $I->assertEquals('transport-sync', $span->context->message['queue_name'] ?? null, 'Проверка имени очереди консюмера Messenger при ошибке');
    }
}
