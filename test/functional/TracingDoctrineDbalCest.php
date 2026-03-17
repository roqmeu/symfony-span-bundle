<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Fixture\Command\DbalOkCommand;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class TracingDoctrineDbalCest
{
    use CommandCestTrait;

    public function testOk(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:dbal-ok', true, DbalOkCommand::class);

        // Ожидаем: 1 console span + 7 DB spans (CREATE, INSERT, SELECT, UPDATE, DELETE, DROP) + 4 Transaction spans
        $this->assertEventsCounts($I, $allEvents, 12, 1);

        // Собираем все DB spans
        $dbSpans = [];
        foreach ($endedSpans as $event) {
            if ($event->span->getType() === SpanBundle::SPAN_TYPE_DB) {
                $dbSpans[] = $event->span;
            }
        }

        $I->assertCount(11, $dbSpans, 'Проверка количества DB-спанов');

        $insertSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->context->db['statement'] ?? '', 'INSERT') === 0) {
                $insertSpan = $span;
                break;
            }
        }

        $I->assertNotNull($insertSpan, 'Проверка наличия INSERT DB-спана');
        $I->assertEquals(SpanBundle::SPAN_TYPE_DB, $insertSpan->getType(), 'Проверка типа INSERT DB-спана');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_POSTGRESQL, $insertSpan->getSubtype(), 'Проверка подтипа INSERT DB-спана');
        $I->assertTrue($insertSpan->isSuccessful(), 'Проверка успешного завершения INSERT DB-спана');
        $I->assertNull($insertSpan->getError(), 'Проверка отсутствия ошибки у INSERT DB-спана');

        $I->assertNotNull($insertSpan->context->db['statement'], 'Проверка наличия SQL statement в контексте INSERT DB-спана');
        $I->assertEquals('sql', $insertSpan->context->db['type'], 'Проверка значения db.type у INSERT DB-спана');
        $I->assertEquals('symfony_span_bundle', $insertSpan->context->db['instance'], 'Проверка значения db.instance у INSERT DB-спана');

        $updateSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->context->db['statement'] ?? '', 'UPDATE') === 0) {
                $updateSpan = $span;
                break;
            }
        }

        $I->assertNotNull($updateSpan, 'Проверка наличия UPDATE DB-спана');

        // Проверяем SELECT span
        $selectSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->context->db['statement'] ?? '', 'SELECT') === 0) {
                $selectSpan = $span;
                break;
            }
        }

        $I->assertNotNull($selectSpan, 'Проверка наличия SELECT DB-спана');

        $updateSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->context->db['statement'] ?? '', 'UPDATE') === 0) {
                $updateSpan = $span;
                break;
            }
        }

        $I->assertNotNull($updateSpan, 'Проверка наличия UPDATE DB-спана');

        // Проверяем DELETE span
        $deleteSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->context->db['statement'] ?? '', 'DELETE') === 0) {
                $deleteSpan = $span;
                break;
            }
        }

        $I->assertNotNull($deleteSpan, 'Проверка наличия DELETE DB-спана');

        foreach ($dbSpans as $span) {
            $I->assertTrue($span->isSuccessful(), 'Проверка успешного завершения DB-спана');
            $I->assertNull($span->getError(), 'Проверка отсутствия ошибки у DB-спана');
        }
    }
}
