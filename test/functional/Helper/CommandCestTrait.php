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

    protected function assertCommand(FunctionalTester $I, array $events, string $command, bool $successful, string $commandClass = ''): void
    {
        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $events;

        $application = $I->getApplication();

        if ($commandClass !== '') {
            $commandService = $I->grabService($commandClass);

            $I->assertNotNull($commandService, "Проверка наличия сервиса команды \"{$commandClass}\".");
        }

        $exitCode = $application->run(
            new StringInput($command),
            new NullOutput()
        );

        $expectedCode = $successful ? Command::SUCCESS : Command::FAILURE;

        $I->assertEquals(
            $expectedCode,
            $exitCode,
            "Проверка кода выхода команды \"{$command}\"."
        );

        $span = $this->getEventSpanByType($endedSpans, SpanBundle::SPAN_TYPE_CONSOLE);

        $I->assertNotNull($span, "Проверка наличия спана типа \"console\" команды \"{$command}\".");

        $I->assertEquals(true, $span->isEnded(), "Проверка завершения спана команды \"{$command}\".");

        if ($successful) {
            $I->assertEquals(true, $span->isSuccessful(), "Проверка успешного завершения спана команды \"{$command}\".");
            $I->assertEquals(null, $span->getError(), "Проверка отсутствия ошибки у спана команды \"{$command}\".");
        } else {
            $I->assertEquals(false, $span->isSuccessful(), "Проверка неуспешного завершения спана команды \"{$command}\".");
            $I->assertNotNull($span->getError(), "Проверка наличия ошибки у спана команды \"{$command}\".");
        }

        $this->assertEventsMinCounts($I, $events, 1, 1);
    }
}
