<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger;

class TracingStamp
{
    public string $traceId;

    public string $parentId;

    public function __construct(string $traceId, string $parentId)
    {
        $this->traceId = $traceId;
        $this->parentId = $parentId;
    }
}
