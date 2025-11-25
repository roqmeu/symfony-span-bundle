<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\GuzzleHttp;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\State\Span;

class TracingMiddleware
{
    protected SpanTracer $spanTracer;

    /**
     * @var callable(RequestInterface, array): PromiseInterface
     */
    private $nextHandler;

    public function __construct(SpanTracer $spanTracer, callable $nextHandler)
    {
        $this->spanTracer = $spanTracer;
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

        $uri = $request->getUri();

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

        $span = new Span("{$request->getMethod()} {$targetName}", SpanBundle::SPAN_TYPE_CLIENT, SpanBundle::SPAN_SUBTYPE_HTTP);

        $span->context->target = [
            'type' => SpanBundle::SPAN_SUBTYPE_HTTP,
            'name' => $targetName,
        ];

        $span->context->http_request = [
            'method' => $request->getMethod(),
            'url' => [
                'domain' => $host,
                'path' => $uri->getPath(),
                'port' => $port,
                'scheme' => $scheme,
            ],
        ];

        $this->spanTracer->startSpan($span);

        return $fn($request, $options)->then(
            function (ResponseInterface $response) use ($span) {
                $statusCode = $response->getStatusCode();

                $span->context->http_response['status_code'] = $statusCode;

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
