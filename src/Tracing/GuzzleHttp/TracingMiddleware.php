<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\GuzzleHttp;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\Tracing\AbstractTracingHttpClient;

class TracingMiddleware extends AbstractTracingHttpClient
{
    /**
     * @var callable(RequestInterface, array): PromiseInterface
     */
    private $nextHandler;

    public function __construct(SpanTracer $spanTracer, callable $nextHandler)
    {
        parent::__construct($spanTracer);

        $this->nextHandler = $nextHandler;
    }

    public static function create(SpanTracer $spanTracer): callable
    {
        return static function (callable $nextHandler) use ($spanTracer): callable {
            return new TracingMiddleware($spanTracer, $nextHandler);
        };
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        $fn = $this->nextHandler;

        if (!$this->spanTracer->hasActiveTrace()) {
            return $fn($request, $options);
        }

        $span = $this->makeRequestSpan($request->getMethod(), $request->getUri());

        $this->spanTracer->startSpan($span, static function (string $key, string $value) use (&$request): void {
            if (!$request->hasHeader($key)) {
                $request = $request->withHeader($key, $value);
            }
        });

        return $fn($request, $options)->then(
            function (ResponseInterface $response) use ($span) {
                $statusCode = $response->getStatusCode();

                $span->context->http_response = ['status_code' => $statusCode];

                $span->setSuccessfulIf($statusCode >= 100 && $statusCode < 400);

                $this->spanTracer->endSpan($span);

                return $response;
            },
            function (\Throwable $error) use ($span) {
                $span->setError($error);

                $this->spanTracer->endSpan($span);

                throw $error;
            }
        );
    }
}
