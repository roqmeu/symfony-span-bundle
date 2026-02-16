<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class PropagationStamp implements StampInterface
{
    public array $data = [];
}
