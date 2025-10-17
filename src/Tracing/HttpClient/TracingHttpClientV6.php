<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\HttpClient;

use Roqmeu\SpanBundle\State\Span;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TracingHttpClientV6 extends TracingHttpClient
{
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }

    protected function tracingResponse(ResponseInterface $response, Span $span): TracingResponse
    {
        return new TracingResponseV6($response, $span, $this->dispatcher);
    }
}
