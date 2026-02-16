<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\HttpClient;

use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\State\Span;
use Symfony\Component\HttpClient\Response\StreamableInterface;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class TracingResponse implements ResponseInterface, StreamableInterface
{
    protected ?SpanTracer $spanTracer;

    public ResponseInterface $response;

    private ?Span $span;

    public function __construct(?SpanTracer $spanTracer, ?Span $span, ResponseInterface $response)
    {
        $this->spanTracer = $spanTracer;
        $this->span = $span;
        $this->response = $response;
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Serializing instances of this class is forbidden.');
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Unserializing instances of this class is forbidden.');
    }

    public function __destruct()
    {
        try {
            if (method_exists($this->response, '__destruct')) {
                $this->response->__destruct();
            }
        } finally {
            $this->finish();
        }
    }

    public function toStream(bool $throw = true)
    {
        if ($this->response instanceof StreamableInterface) {
            return $this->response->toStream($throw);
        }

        throw new \BadMethodCallException('The inner response does not support toStream().');
    }

    public function getStatusCode(): int
    {
        try {
            return $this->response->getStatusCode();
        } catch (\Throwable $error) {
            if ($this->span !== null) {
                $this->span->setError($error);
            }

            throw $error;
        } finally {
            $this->finish();
        }
    }

    public function getHeaders(bool $throw = true): array
    {
        try {
            return $this->response->getHeaders($throw);
        } catch (\Throwable $error) {
            if ($this->span !== null) {
                $this->span->setError($error);
            }

            throw $error;
        } finally {
            $this->finish();
        }
    }

    public function getContent(bool $throw = true): string
    {
        try {
            return $this->response->getContent($throw);
        } catch (\Throwable $error) {
            if ($this->span !== null) {
                $this->span->setError($error);
            }

            throw $error;
        } finally {
            $this->finish();
        }
    }

    public function toArray(bool $throw = true): array
    {
        try {
            return $this->response->toArray($throw);
        } catch (\Throwable $error) {
            if ($this->span !== null) {
                $this->span->setError($error);
            }

            throw $error;
        } finally {
            $this->finish();
        }
    }

    public function cancel(): void
    {
        try {
            $this->response->cancel();
        } finally {
            $this->finish();
        }
    }

    private function finish(): void
    {
        if ($this->span === null || $this->spanTracer === null) {
            return;
        }

        $info = $this->response->getInfo();

        if (!\is_array($info) || \count($info) === 0) {
            return;
        }

        $start = $info['start_time'] ?? 0;

        if ($start > 0) {
            $this->span->setStartTime($start);
        }

        $duration = $info['total_time'] ?? 0;

        if ($start > 0 && $duration > 0) {
            $end = $start + $duration;
        } else {
            $end = microtime(true);
        }

        $this->span->setEndTime($end);

        $statusCode = (int)($info['http_code'] ?? 0);

        if ($statusCode > 0) {
            $this->span->context->http_response = ['status_code' => $statusCode];

            $this->span->setSuccessfulIf($statusCode >= 100 && $statusCode < 400);
        }

        $this->spanTracer->endSpan($this->span);

        $this->spanTracer = $this->span = null;
    }

    /**
     * @param iterable<ResponseInterface> $responses
     *
     * @return \Generator<ResponseInterface, ChunkInterface>
     */
    public static function stream(HttpClientInterface $client, iterable $responses, ?float $timeout): \Generator
    {
        $tracingResponseMap = [];
        $innerResponses = [];

        foreach ($responses as $response) {
            if ($response instanceof self) {
                $tracingResponseMap[\spl_object_id($response->response)] = $response;

                $innerResponses[] = $response->response;
            } else {
                $innerResponses[] = $response;
            }
        }

        foreach ($client->stream($innerResponses, $timeout) as $response => $chunk) {
            $tracingResponse = $tracingResponseMap[\spl_object_id($response)] ?? null;

            if ($tracingResponse !== null) {
                $tracingResponse->finish();

                yield $tracingResponse => $chunk;
            } else {
                yield $response => $chunk;
            }
        }
    }
}
