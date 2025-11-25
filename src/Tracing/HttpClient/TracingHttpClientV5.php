<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\HttpClient;

use Roqmeu\SpanBundle\State\Span;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TracingHttpClientV5 extends TracingHttpClient
{
    public function withOptions(array $options): HttpClientInterface
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }

    protected function tracingResponse(ResponseInterface $response, Span $span): TracingResponse
    {
        return new TracingResponseV5($this->spanTracer, $span, $response);
    }
}
