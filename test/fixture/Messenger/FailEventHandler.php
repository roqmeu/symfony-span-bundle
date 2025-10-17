<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Messenger;

class FailEventHandler
{
    public function __invoke(FailEvent $event): void
    {
        throw new \RuntimeException('messenger boom');
    }
}
