<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\HttpClient;

class TracingResponseV6 extends TracingResponse
{
    public function getInfo(string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }
}
