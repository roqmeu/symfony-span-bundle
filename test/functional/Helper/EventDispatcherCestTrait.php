<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Functional\Helper;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\Test\Support\FunctionalTester;
use Roqmeu\SpanBundle\Transport\Event\SpanEndedEvent;
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceEndedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceStartedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait EventDispatcherCestTrait
{
    /**
     * @return array{0: array<SpanStartedEvent>, 1: array<SpanEndedEvent>, 2: array<TraceStartedEvent>, 3: array<TraceEndedEvent>}
     */
    protected function grabEvents(FunctionalTester $I): array
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $I->grabService('event_dispatcher');

        $startedSpans = [];
        $dispatcher->addListener(SpanStartedEvent::class, static function ($event) use (&$startedSpans): void {
            $startedSpans[] = $event;
        });
        $endedSpans = [];
        $dispatcher->addListener(SpanEndedEvent::class, static function ($event) use (&$endedSpans): void {
            $endedSpans[] = $event;
        });
        $startedTraces = [];
        $dispatcher->addListener(TraceStartedEvent::class, static function ($event) use (&$startedTraces): void {
            $startedTraces[] = $event;
        });
        $endedTraces = [];
        $dispatcher->addListener(TraceEndedEvent::class, static function ($event) use (&$endedTraces): void {
            $endedTraces[] = $event;
        });

        return [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces];
    }

    /**
     * @param array{0: array<SpanStartedEvent>, 1: array<SpanEndedEvent>, 2: array<TraceStartedEvent>, 3: array<TraceEndedEvent>} $allEvents
     */
    protected function assertEventsCounts(FunctionalTester $I, array $allEvents, int $spanCount, int $traceCount): void
    {
        [$startedSpans, $endedSpans, $startedTraces, $endedTraces] = $allEvents;

        $startedSpansCount = \count($startedSpans);
        $endedSpansCount = \count($endedSpans);
        $I->assertEquals(
            $spanCount,
            $startedSpansCount,
            "Ожидали \"{$spanCount}\" событий SpanStartedEvent"
        );
        $I->assertEquals(
            $spanCount,
            $endedSpansCount,
            "Ожидали \"{$spanCount}\" событий SpanEndedEvent"
        );

        $startedTracesCount = \count($startedTraces);
        $endedTracesCount = \count($endedTraces);
        $I->assertEquals(
            $traceCount,
            $startedTracesCount,
            "Ожидали \"{$traceCount}\" событий TraceStartedEvent"
        );
        $I->assertEquals(
            $traceCount,
            $endedTracesCount,
            "Ожидали \"{$traceCount}\" событий TraceEndedEvent"
        );
    }

    /**
     * @param array{0: array<SpanStartedEvent>, 1: array<SpanEndedEvent>, 2: array<TraceStartedEvent>, 3: array<TraceEndedEvent>} $allEvents
     */
    protected function assertEventsMinCounts(FunctionalTester $I, array $allEvents, int $spanCount, int $traceCount): void
    {
        [&$startedSpans, &$endedSpans, &$startedTraces, &$endedTraces] = $allEvents;

        $startedSpansCount = \count($startedSpans);
        $endedSpansCount = \count($endedSpans);
        $I->assertGreaterThanOrEqual(
            $spanCount,
            $startedSpansCount,
            "Ожидали минимум \"{$spanCount}\" событий SpanStartedEvent"
        );
        $I->assertGreaterThanOrEqual(
            $spanCount,
            $endedSpansCount,
            "Ожидали минимум \"{$spanCount}\" событий SpanEndedEvent"
        );

        $startedTracesCount = \count($startedTraces);
        $endedTracesCount = \count($endedTraces);
        $I->assertGreaterThanOrEqual(
            $traceCount,
            $startedTracesCount,
            "Ожидали минимум \"{$traceCount}\" событий TraceStartedEvent"
        );
        $I->assertGreaterThanOrEqual(
            $traceCount,
            $endedTracesCount,
            "Ожидали минимум \"{$traceCount}\" событий TraceEndedEvent"
        );
    }

    /**
     * @param array<SpanStartedEvent>|array<SpanEndedEvent> $events
     */
    protected function getEventSpanByType(array $events, string $type): ?Span
    {
        foreach ($events as $event) {
            if ($event->span->getType() === $type) {
                return $event->span;
            }
        }

        return null;
    }
}
