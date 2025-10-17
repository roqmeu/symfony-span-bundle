<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\HttpClient;

use GuzzleHttp\Psr7\Uri;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Tracing\SpanTracingTrait;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

abstract class TracingHttpClient implements HttpClientInterface, ResetInterface, LoggerAwareInterface
{
    use SpanTracingTrait;

    protected HttpClientInterface $client;

    public function __construct(
        Dispatcher $dispatcher,
        TransactionPool $tracePool,
        HttpClientInterface $client
    ) {
        $this->dispatcher = $dispatcher;
        $this->tracePool = $tracePool;
        $this->client = $client;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $parent = $this->tracePool->getCurrentSpan();

        if ($parent === null) {
            return $this->client->request($method, $url, $options);
        }

        $uri = new Uri($url);
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        $targetName = $host;

        if ($scheme !== '') {
            $targetName = "{$scheme}://{$targetName}";
        }
        if ($port !== null) {
            $targetName = "{$targetName}:{$port}";
        }

        $span = $this->beginSpan(
            $parent,
            "{$method} {$targetName}",
            SpanBundle::SPAN_TYPE_CLIENT,
            SpanBundle::SPAN_SUBTYPE_HTTP
        );

        $span->context->target = [
            'type' => SpanBundle::SPAN_SUBTYPE_HTTP,
            'name' => $targetName,
        ];

        $span->context->http_request = [
            'method' => $method,
            'url' => [
                'scheme' => $scheme,
                'domain' => $host,
                'port' => (string)$port,
                'path' => $uri->getPath(),
            ],
        ];

        return $this->tracingResponse($this->client->request($method, $url, $options), $span);
    }

    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        $innerResponses = [];

        if (is_iterable($responses)) {
            foreach ($responses as $response) {
                $innerResponses[] = $response instanceof TracingResponse ? $response->response : $response;
            }
        }

        return $this->client->stream($innerResponses, $timeout);
    }

    public function reset(): void
    {
        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        if ($this->client instanceof LoggerAwareInterface) {
            $this->client->setLogger($logger);
        }
    }

    abstract protected function tracingResponse(ResponseInterface $response, Span $span): TracingResponse;
}
