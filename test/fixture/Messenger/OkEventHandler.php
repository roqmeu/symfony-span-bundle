<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Messenger;

class OkEventHandler
{
    public function __invoke(OkEvent $event): void
    {
        // ничего не делаем, просто успешная обработка
    }
}
