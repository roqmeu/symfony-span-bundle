<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional\Helper;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

trait CommandCestTrait
{
    use EventDispatcherCestTrait;

    protected function assertCommand(FunctionalTester $I, array $events, string $command, bool $successful, ?callable $beforeHook = null): void
    {
        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $events;

        $application = $I->getApplication();

        if (\is_callable($beforeHook)) {
            $beforeHook($application);
        }

        $exitCode = $application->run(
            new StringInput($command),
            new NullOutput()
        );

        $expectedCode = $successful ? Command::SUCCESS : Command::FAILURE;

        $I->assertEquals(
            $expectedCode,
            $exitCode,
            "Ожидали \"{$expectedCode}\" код выхода для команды \"{$command}\"."
        );

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSOLE);

        $I->assertNotNull($span, 'Ожидали найти спан типа "console"');

        $I->assertEquals((explode(' ', $command))[0], $span->getName(), "Ожидали \"{$command}\" имя команды.");
        $I->assertEquals(SpanBundle::SPAN_TYPE_CONSOLE, $span->getType(), "// TODO");
        $I->assertEquals(true, $span->isEnded(), "// TODO");

        if ($successful) {
            $I->assertEquals(true, $span->isSuccessful(), "// TODO");
            $I->assertEquals(null, $span->getError(), "// TODO");
        } else {
            $I->assertEquals(false, $span->isSuccessful(), "// TODO");
            $I->assertNotNull($span->getError(), "// TODO");
        }

        $this->assertEventsMinCounts($I, $events, 1, 1);
    }
}
