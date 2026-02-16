<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\HttpClient;

use GuzzleHttp\Psr7\Uri;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\Tracing\AbstractTracingHttpClient;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

abstract class TracingHttpClient extends AbstractTracingHttpClient implements HttpClientInterface, ResetInterface, LoggerAwareInterface
{
    protected HttpClientInterface $client;

    public function __construct(SpanTracer $spanTracer, HttpClientInterface $client)
    {
        parent::__construct($spanTracer);

        $this->client = $client;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->spanTracer->hasActiveTrace()) {
            return $this->client->request($method, $url, $options);
        }

        $span = $this->makeRequestSpan($method, new Uri($url));

        $headers = $options['headers'] ?? [];

        $this->spanTracer->startSpan($span, static function (string $key, string $value) use (&$headers): void {
            if (!\array_key_exists($key, $headers)) {
                $headers[$key] = $value;
            }
        });

        $options['headers'] = $headers;

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
