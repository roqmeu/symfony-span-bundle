<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\Test\Fixture\Command\CommandFail;
use Roqmeu\SpanBundle\Test\Fixture\Command\CommandOk;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;
use Symfony\Component\HttpKernel\Kernel;

class TracingCommandCest
{
    use CommandCestTrait;

    public function testOk(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:command-ok', true, CommandOk::class);

        $this->assertEventsCounts($I, $allEvents, 1, 1);

        $span = $startedSpans[0]->span;

        $I->assertEquals('app:test:command-ok', $span->context->command['name'] ?? null, 'Проверка имени успешной команды в контексте спана');

        $I->assertEqualsCanonicalizing(
            $span->context->command,
            [
                'name' => 'app:test:command-ok'
            ],
            'Проверка контекста команды для успешного запуска.'
        );
        $I->assertEqualsCanonicalizing(
            $span->context->framework,
            [
                'debug' => false,
                'environment' => 'test',
                'framework' => 'symfony',
                'version' => Kernel::VERSION
            ],
            'Проверка контекста Symfony framework для успешного запуска.'
        );
        $I->assertEqualsCanonicalizing(
            $span->context->process,
            [
                'executable' => '/usr/local/bin/php',
                'interactive' => \posix_isatty(\STDIN) || \posix_isatty(\STDOUT),
                'pid' => \posix_getpid(),
                'parent_pid' => \posix_getppid(),
                'runtime_name' => 'cli',
                'runtime_version' => PHP_VERSION
            ],
            'Проверка контекста процесса для успешного запуска команды.'
        );
    }

    public function testError(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:command-fail', false, CommandFail::class);

        $this->assertEventsCounts($I, $allEvents, 1, 1);

        $span = $startedSpans[0]->span;

        $I->assertEquals('app:test:command-fail', $span->context->command['name'] ?? null, 'Проверка имени неуспешной команды в контексте спана');

        $I->assertInstanceOf(\RuntimeException::class, $span->getError(), 'Проверка типа ошибки неуспешной команды');
    }
}
