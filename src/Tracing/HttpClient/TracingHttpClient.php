<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\HttpClient;

use GuzzleHttp\Psr7\Uri;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;
use Roqmeu\SpanBundle\State\Span;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

abstract class TracingHttpClient implements HttpClientInterface, ResetInterface, LoggerAwareInterface
{
    use SpanTracerAwareTrait;

    protected HttpClientInterface $client;

    public function __construct(SpanTracer $spanTracer, HttpClientInterface $client)
    {
        $this->spanTracer = $spanTracer;
        $this->client = $client;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->spanTracer->hasActiveTrace()) {
            return $this->client->request($method, $url, $options);
        }

        $uri = new Uri($url);

        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        $targetName = $host;

        if ($port === null && $scheme !== '') {
            $schemePort = \getservbyname($scheme, 'tcp');

            if ($schemePort !== false) {
                $port = $schemePort;
            }
        }

        if ($port !== null) {
            $targetName = "{$targetName}:{$port}";
        }

        $span = new Span("{$method} {$targetName}", SpanBundle::SPAN_TYPE_CLIENT, SpanBundle::SPAN_SUBTYPE_HTTP);

        $span->context->target = [
            'type' => SpanBundle::SPAN_SUBTYPE_HTTP,
            'name' => $targetName,
        ];

        $span->context->http_request = [
            'method' => $method,
            'url' => [
                'domain' => $host,
                'path' => $uri->getPath(),
                'port' => $port,
                'scheme' => $scheme,
            ],
        ];

        $this->spanTracer->startSpan($span);

        return $this->tracingResponse($this->client->request($method, $url, $options), $span);
    }

    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        return new ResponseStream(TracingResponse::stream($this->client, $responses, $timeout));
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
