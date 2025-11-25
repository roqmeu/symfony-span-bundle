<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional;

use Roqmeu\SpanBundle\Profiling\SpanProfilerHandler;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\Test\Functional\Helper\CommandCestTrait;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;

class ProfilerCest
{
    use CommandCestTrait;

    public function testProfiler(FunctionalTester $I): void
    {
        // Enable ProfilerHandler
        $handler = $I->grabService(SpanProfilerHandler::class);
        $handler->enabled = true;

        $allEvents = $this->grabEvents($I);

        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $this->assertCommand($I, $allEvents, 'app:test:profiler', true);

        $awaiting = [
            ['profilerLevel1', 0.02],
            ['profilerLevel2', 0.04],
            ['profilerLevel3', 0.08],
            ['profilerLevel2', 0.04],
            ['profilerLevel3', 0.16],
            ['SpanProfiler', 0.34]
        ];

        $idx = 0;

        foreach ($endedSpans as $endedSpan) {
            $span = $endedSpan->span;

            if ($span->getSubtype() !== SpanBundle::SPAN_SUBTYPE_PROFILE) {
                continue;
            }

            $I->assertIsArray($awaiting[$idx] ?? null, 'TODO');

            $I->assertStringContainsString($awaiting[$idx][0], $span->getName(), 'TODO');

            $I->assertEquals(SpanBundle::SPAN_TYPE_INTERNAL, $span->getType(), 'TODO');

            $I->assertGreaterThan($awaiting[$idx][1] - 0.02, $span->getEndTime() - $span->getStartTime(), 'TODO');
            $I->assertLessThan($awaiting[$idx][1] + 0.02, $span->getEndTime() - $span->getStartTime(), 'TODO');

            $idx++;
        }

        // Disable ProfilerHandler
        $handler->enabled = false;
    }
}
