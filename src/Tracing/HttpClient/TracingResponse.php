<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\HttpClient;

use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;
use Symfony\Component\HttpClient\Response\StreamableInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class TracingResponse implements ResponseInterface, StreamableInterface
{
    public ResponseInterface $response;

    private ?Span $span;
    private ?Dispatcher $dispatcher;

    public function __construct(ResponseInterface $response, Span $span, Dispatcher $dispatcher)
    {
        $this->response = $response;
        $this->span = $span;
        $this->dispatcher = $dispatcher;
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
        if (method_exists($this->response, '__destruct')) {
            $this->response->__destruct();
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
                $this->span->error = $error;
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
                $this->span->error = $error;
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
                $this->span->error = $error;
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
                $this->span->error = $error;
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
        if ($this->span === null || $this->dispatcher === null) {
            return;
        }

        $info = $this->response->getInfo();

        if (!is_array($info) || count($info) === 0) {
            return;
        }

        $start = $info['start_time'] ?? 0;

        if ($start > $this->span->start) {
            $this->span->start = $start;
        }

        $duration = $info['total_time'] ?? 0;

        if ($duration > 0) {
            $end = $start + $duration;
        } else {
            $end = (int)microtime(true);
        }

        $statusCode = (int)($info['http_code'] ?? 0);

        if ($statusCode > 0) {
            $this->span->context->http_response = [
                'status_code' => $statusCode,
            ];

            if ($this->span->successful === null) {
                $this->span->successful = $statusCode >= 100 && $statusCode < 400;
            }
        }

        $this->span->end($end);
        $this->dispatcher->spanFinished($this->span);

        $this->span = null;
        $this->dispatcher = null;
    }
}
