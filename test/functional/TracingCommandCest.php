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

        $I->assertEqualsCanonicalizing(
            $span->context->command,
            ['name' => 'app:test:command-ok'],
            'TODO'
        );
        $I->assertEqualsCanonicalizing(
            $span->context->framework,
            [
                'debug' => false,
                'environment' => 'test',
                'framework' => 'symfony',
                'version' => Kernel::VERSION
            ],
            'TODO'
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
            'TODO'
        );
    }

    public function testError(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:command-fail', false, CommandFail::class);

        $this->assertEventsCounts($I, $allEvents, 1, 1);

        $event = $startedSpans[0];

        $I->assertInstanceOf(\RuntimeException::class, $event->span->getError(), 'Ожидали RuntimeException');
    }
}
