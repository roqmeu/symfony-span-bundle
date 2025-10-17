<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\GuzzleHttp;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Tracing\SpanTracingTrait;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;

class TracingMiddleware
{
    use SpanTracingTrait;

    /**
     * @var callable(RequestInterface, array): PromiseInterface
     */
    private $nextHandler;

    public function __construct(
        Dispatcher $dispatcher,
        TransactionPool $tracePool,
        callable $nextHandler
    ) {
        $this->dispatcher = $dispatcher;
        $this->tracePool = $tracePool;
        $this->nextHandler = $nextHandler;
    }

    public static function create(Dispatcher $dispatcher, TransactionPool $tracePool): callable
    {
        return static function (callable $nextHandler) use ($dispatcher, $tracePool): callable {
            return new TracingMiddleware($dispatcher, $tracePool, $nextHandler);
        };
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        $fn = $this->nextHandler;

        $parent = $this->tracePool->getCurrentSpan();

        if ($parent === null) {
            return $fn($request, $options);
        }

        $uri = $request->getUri();
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
            "{$request->getMethod()} {$targetName}",
            SpanBundle::SPAN_TYPE_CLIENT,
            SpanBundle::SPAN_SUBTYPE_HTTP
        );

        $span->context->target = [
            'type' => SpanBundle::SPAN_SUBTYPE_HTTP,
            'name' => $targetName,
        ];

        $span->context->http_request = [
            'method' => $request->getMethod(),
            'url' => [
                'scheme' => $scheme,
                'domain' => $host,
                'port' => (string)$port,
                'path' => $uri->getPath(),
            ],
        ];

        return $fn($request, $options)->then(
            function (ResponseInterface $response) use ($span) {
                $statusCode = $response->getStatusCode();
                $span->context->http_response['status_code'] = $statusCode;

                if ($span->successful === null) {
                    $span->successful = $statusCode >= 100 && $statusCode < 400;
                }

                $this->endSpan($span);

                return $response;
            },
            function (\Throwable $error) use ($span) {
                $span->error = $error;
                $span->successful = false;

                $this->endSpan($span);

                throw $error;
            }
        );
    }
}
