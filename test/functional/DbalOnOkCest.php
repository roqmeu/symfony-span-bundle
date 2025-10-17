<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\SpanPool;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;
use Roqmeu\SpanBundle\Transport\Event\SpanFinishedEvent;
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DbalOnOkCest
{
    public function dispatchEvent(FunctionalTester $I): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $I->grabService('event_dispatcher');

        /** @var SpanPool $spanPool */
        $spanPool = $I->grabService(SpanPool::class);
        /** @var TransactionPool $tracePool */
        $tracePool = $I->grabService(TransactionPool::class);

        $application = $I->getApplication();

        $startedSpans = [];
        $dispatcher->addListener(SpanStartedEvent::class, static function ($event) use (&$startedSpans): void {
            $startedSpans[] = $event;
        });
        $finishedSpans = [];
        $dispatcher->addListener(SpanFinishedEvent::class, static function ($event) use (&$finishedSpans): void {
            $finishedSpans[] = $event;
        });

        $exitCode = $application->run(
            new StringInput('app:test:dbal-ok'),
            new BufferedOutput()
        );
        $I->assertEquals(0, $exitCode, 'Ожидали нулевой код выхода для команды');

        // Ожидаем: 1 console span + 7 DB spans (CREATE, INSERT, SELECT, UPDATE, DELETE, DROP) + 4 Transaction spans
        $I->assertEquals(12, count($startedSpans), 'Ожидали 12 спанов (console + DBAL запросы)');
        $I->assertEquals(12, count($finishedSpans), 'Ожидали 12 завершенных спанов');

        // Собираем все DB spans
        $dbSpans = [];
        foreach ($finishedSpans as $event) {
            if ($event->span->type === SpanBundle::SPAN_TYPE_DB) {
                $dbSpans[] = $event->span;
            }
        }

        $I->assertEquals(11, count($dbSpans), 'Ожидали минимум 11 DB span\'ов');

        $insertSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->name, 'INSERT') === 0) {
                $insertSpan = $span;
                break;
            }
        }

        $I->assertNotNull($insertSpan, 'Ожидали найти INSERT INTO span');
        $I->assertEquals(SpanBundle::SPAN_TYPE_DB, $insertSpan->type, 'Ожидали тип db');
        $I->assertEquals(SpanBundle::SPAN_SUBTYPE_POSTGRESQL, $insertSpan->subtype, 'Ожидали подтип postgresql');
        $I->assertTrue($insertSpan->successful, 'Ожидали успешное выполнение');
        $I->assertNull($insertSpan->error, 'Ожидали отсутствие ошибки');

        $I->assertNotNull($insertSpan->context->db_statement, 'Ожидали SQL statement в context');
        $I->assertEquals('sql', $insertSpan->context->db_type, 'Ожидали db_type = sql');
        $I->assertEquals('spanbundle', $insertSpan->context->db_instance, 'Ожидали db_instance = spanbundle');

        $I->assertIsArray($insertSpan->context->target, 'Ожидали target как массив');
        $I->assertEquals('postgresql', $insertSpan->context->target['type'], 'Ожидали target.type = postgresql');
        $I->assertEquals('spanbundle', $insertSpan->context->target['name'], 'Ожидали target.name = spanbundle');

        $updateSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->name, 'UPDATE') === 0) {
                $updateSpan = $span;
                break;
            }
        }

        $I->assertNotNull($updateSpan, 'Ожидали найти UPDATE span');
        $I->assertStringContainsString('UPDATE', $updateSpan->name, 'Ожидали UPDATE в имени span');
        $I->assertStringContainsString('test_users', $updateSpan->name, 'Ожидали имя таблицы test_users в span name');

        // Проверяем SELECT span
        $selectSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->name, 'SELECT') === 0) {
                $selectSpan = $span;
                break;
            }
        }

        $I->assertNotNull($selectSpan, 'Ожидали найти SELECT span');
        $I->assertStringContainsString('SELECT', $selectSpan->name, 'Ожидали SELECT в имени span');
        $I->assertStringContainsString('test_users', $selectSpan->name, 'Ожидали имя таблицы test_users в span name');

        $updateSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->name, 'UPDATE') === 0) {
                $updateSpan = $span;
                break;
            }
        }

        $I->assertNotNull($updateSpan, 'Ожидали найти UPDATE span');
        $I->assertStringContainsString('UPDATE', $updateSpan->name, 'Ожидали UPDATE в имени span');
        $I->assertStringContainsString('test_users', $updateSpan->name, 'Ожидали имя таблицы test_users в span name');

        // Проверяем DELETE span
        $deleteSpan = null;
        foreach ($dbSpans as $span) {
            if (\strpos($span->name, 'DELETE') === 0) {
                $deleteSpan = $span;
                break;
            }
        }

        $I->assertNotNull($deleteSpan, 'Ожидали найти DELETE span');
        $I->assertStringContainsString('DELETE', $deleteSpan->name, 'Ожидали DELETE в имени span');
        $I->assertStringContainsString('test_users', $deleteSpan->name, 'Ожидали имя таблицы test_users в span name');

        foreach ($dbSpans as $span) {
            $I->assertTrue($span->successful, "Ожидали успешное выполнение span: {$span->name}");
            $I->assertNull($span->error, "Ожидали отсутствие ошибки для span: {$span->name}");
        }

        $I->assertTrue($spanPool->empty(), 'Ожидали пустой SpanPool');
        $I->assertTrue($tracePool->empty(), 'Ожидали пустой TracePool');
    }
}
