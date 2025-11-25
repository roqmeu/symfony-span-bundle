<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class DbalOnOkCest
{
    use CommandCestTrait;

    public function dispatchEvent(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:dbal-ok', true);

        // Ожидаем: 1 console span + 7 DB spans (CREATE, INSERT, SELECT, UPDATE, DELETE, DROP) + 4 Transaction spans
        $this->assertEventsCounts($I, $allEvents, 12, 1);

        // Собираем все DB spans
        $dbSpans = [];
        foreach ($endedSpans as $event) {
            if ($event->span->getType() === SpanBundle::SPAN_TYPE_DB) {
                $dbSpans[] = $event->span;
            }
        }

        $I->assertCount(11, $dbSpans, 'Ожидали минимум 11 DB span\'ов');

        $insertSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->getName(), 'INSERT') === 0) {
                $insertSpan = $span;
                break;
            }
        }

        $I->assertNotNull($insertSpan, 'Ожидали найти INSERT INTO span');
        $I->assertEquals(SpanBundle::SPAN_TYPE_DB, $insertSpan->getType(), 'Ожидали тип db');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_POSTGRESQL, $insertSpan->getSubtype(), 'Ожидали подтип postgresql');
        $I->assertTrue($insertSpan->isSuccessful(), 'Ожидали успешное выполнение');
        $I->assertNull($insertSpan->getError(), 'Ожидали отсутствие ошибки');

        $I->assertNotNull($insertSpan->context->db['statement'], 'Ожидали SQL statement в context');
        $I->assertEquals('sql', $insertSpan->context->db['type'], 'Ожидали db.type = sql');
        $I->assertEquals('symfony_span_bundle', $insertSpan->context->db['instance'], 'Ожидали db.instance = symfony_span_bundle');

        $I->assertIsArray($insertSpan->context->target, 'Ожидали target как массив');
        $I->assertEquals('postgresql', $insertSpan->context->target['type'], 'Ожидали target.type = postgresql');
        $I->assertEquals('symfony_span_bundle', $insertSpan->context->target['name'], 'Ожидали target.name = symfony_span_bundle');

        $updateSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->getName(), 'UPDATE') === 0) {
                $updateSpan = $span;
                break;
            }
        }

        $I->assertNotNull($updateSpan, 'Ожидали найти UPDATE span');
        $I->assertStringContainsString('UPDATE', $updateSpan->getName(), 'Ожидали UPDATE в имени span');
        $I->assertStringContainsString('test_users', $updateSpan->getName(), 'Ожидали имя таблицы test_users в span name');

        // Проверяем SELECT span
        $selectSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->getName(), 'SELECT') === 0) {
                $selectSpan = $span;
                break;
            }
        }

        $I->assertNotNull($selectSpan, 'Ожидали найти SELECT span');
        $I->assertStringContainsString('SELECT', $selectSpan->getName(), 'Ожидали SELECT в имени span');
        $I->assertStringContainsString('test_users', $selectSpan->getName(), 'Ожидали имя таблицы test_users в span name');

        $updateSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->getName(), 'UPDATE') === 0) {
                $updateSpan = $span;
                break;
            }
        }

        $I->assertNotNull($updateSpan, 'Ожидали найти UPDATE span');
        $I->assertStringContainsString('UPDATE', $updateSpan->getName(), 'Ожидали UPDATE в имени span');
        $I->assertStringContainsString('test_users', $updateSpan->getName(), 'Ожидали имя таблицы test_users в span name');

        // Проверяем DELETE span
        $deleteSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->getName(), 'DELETE') === 0) {
                $deleteSpan = $span;
                break;
            }
        }

        $I->assertNotNull($deleteSpan, 'Ожидали найти DELETE span');
        $I->assertStringContainsString('DELETE', $deleteSpan->getName(), 'Ожидали DELETE в имени span');
        $I->assertStringContainsString('test_users', $deleteSpan->getName(), 'Ожидали имя таблицы test_users в span name');

        foreach ($dbSpans as $span) {
            $I->assertTrue($span->isSuccessful(), "Ожидали успешное выполнение span: {$span->getName()}");
            $I->assertNull($span->getError(), "Ожидали отсутствие ошибки для span: {$span->getName()}");
        }
    }
}
