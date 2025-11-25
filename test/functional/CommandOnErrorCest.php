<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class CommandOnErrorCest
{
    use CommandCestTrait;

    public function testErrorCommand(FunctionalTester $I): void
    {
        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:command-fail', false);

        $this->assertEventsCounts($I, $allEvents, 1, 1);

        $event = $startedSpans[0];

        $I->assertInstanceOf(\RuntimeException::class, $event->span->getError(), 'Ожидали RuntimeException');
    }
}
