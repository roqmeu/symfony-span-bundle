<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Fixture\Command\HttpClientOkCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\HttpClientScopedOkCommand;
use Roqmeu\SpanBundle\Test\Fixture\Command\HttpClientStreamOkCommand;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class TracingHttpClientCest
{
    use CommandCestTrait;

    public function testOk(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:http-client-ok', true, HttpClientOkCommand::class);

        // Ожидаем 2 спана: один для команды и один HTTP запрос
        $this->assertEventsCounts($I, $allEvents, 2, 1);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CLIENT);

        $I->assertNotNull($span, 'Проверка наличия спана Symfony HTTP-запроса');

        $I->assertEquals(SpanBundle::SPAN_TYPE_CLIENT, $span->getType(), 'Проверка типа спана Symfony HTTP-запроса');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_HTTP, $span->getSubtype(), 'Проверка подтипа спана Symfony HTTP-запроса');
        $I->assertNotNull($span->getEndTime(), 'Проверка времени завершения спана Symfony HTTP-запроса');

        $I->assertEquals('GET', $span->context->http_request['method'] ?? null, 'Проверка HTTP-метода Symfony HTTP-запроса');
        $I->assertEquals('span-bundle.lan', $span->context->http_request['url']['domain'] ?? null, 'Проверка домена Symfony HTTP-запроса');
        $I->assertEquals('/ok', $span->context->http_request['url']['path'] ?? null, 'Проверка пути Symfony HTTP-запроса');
        $I->assertEquals(80, $span->context->http_request['url']['port'] ?? null, 'Проверка порта Symfony HTTP-запроса');
        $I->assertEquals('http', $span->context->http_request['url']['scheme'] ?? null, 'Проверка схемы Symfony HTTP-запроса');
    }

    public function testScopedOk(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:http-client-scoped-ok', true, HttpClientScopedOkCommand::class);

        // Ожидаем 2 спана: один для команды и один HTTP запрос
        $this->assertEventsCounts($I, $allEvents, 2, 1);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CLIENT);

        $I->assertNotNull($span, 'Проверка наличия спана Symfony HTTP-запроса');

        $I->assertEquals(SpanBundle::SPAN_TYPE_CLIENT, $span->getType(), 'Проверка типа спана Symfony HTTP-запроса');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_HTTP, $span->getSubtype(), 'Проверка подтипа спана Symfony HTTP-запроса');
        $I->assertNotNull($span->getEndTime(), 'Проверка времени завершения спана Symfony HTTP-запроса');

        $I->assertEquals('scoped.span-bundle.lan', $span->context->http_request['url']['domain'] ?? null, 'Проверка домена scoped Symfony HTTP-запроса');
    }

    public function testStreamOk(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:http-client-stream-ok', true, HttpClientStreamOkCommand::class);

        // Ожидаем 3 спана: один для команды и два HTTP запроса
        $this->assertEventsCounts($I, $allEvents, 3, 1);

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CLIENT);

        $I->assertNotNull($span, 'Проверка наличия спана Symfony HTTP-запроса');

        $I->assertEquals(SpanBundle::SPAN_TYPE_CLIENT, $span->getType(), 'Проверка типа спана Symfony HTTP-запроса');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_HTTP, $span->getSubtype(), 'Проверка подтипа спана Symfony HTTP-запроса');
        $I->assertNotNull($span->getEndTime(), 'Проверка времени завершения спана Symfony HTTP-запроса');

        $I->assertEquals('span-bundle.lan', $span->context->http_request['url']['domain'] ?? null, 'Проверка домена Symfony HTTP-запроса при стриминге');
    }
}
