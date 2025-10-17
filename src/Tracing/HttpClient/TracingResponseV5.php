<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\HttpClient;

class TracingResponseV5 extends TracingResponse
{
    public function getInfo(string $type = null)
    {
        return $this->response->getInfo($type);
    }
}
