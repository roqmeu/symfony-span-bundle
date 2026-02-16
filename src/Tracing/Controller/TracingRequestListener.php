<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Controller;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;
use Roqmeu\SpanBundle\State\Span;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;
use Symfony\Contracts\Service\ResetInterface;

class TracingRequestListener implements EventSubscriberInterface, ResetInterface
{
    use SpanTracerAwareTrait;

    public function __construct(SpanTracer $spanTracer)
    {
        $this->spanTracer = $spanTracer;
    }

    /**
     * @var array<int, Span>
     */
    public array $spanPool = [];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 1024],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
            KernelEvents::EXCEPTION => ['onKernelException', 1024],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $port = $request->getPort();

        if ($port === null) {
            $port = $request->isSecure() ? 443 : 80;
        }

        $span = new Span(SpanBundle::SPAN_TYPE_SERVER, SpanBundle::SPAN_SUBTYPE_HTTP);

        $span->context->http_request = [
            'method' => $request->getMethod(),
            'route' => $this->getRouteName($request),
            'url' => [
                'domain' => $request->getHost(),
                'path' => $request->getBaseUrl() . $request->getPathInfo(),
                'port' => (int)$port,
                'scheme' => $request->getScheme(),
            ],
        ];

        if ($event->isMainRequest()) {
            $this->spanPool[$this->getRequestId($request)] = $span;

            $headers = $request->headers;

            $this->spanTracer->startTraceSpan($span, static function (string $key) use ($headers): ?string {
                return ((string)($headers->get($key, ''))) ?: null;
            });

            return;
        }

        if ($this->spanTracer->hasActiveTrace()) {
            $this->spanPool[$this->getRequestId($request)] = $span;

            $this->spanTracer->startSpan($span);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $span = $this->spanPool[$this->getRequestId($event->getRequest())] ?? null;

        if ($span === null) {
            return;
        }

        $statusCode = $event->getResponse()->getStatusCode();

        $span->context->http_response = [
            'status_code' => $statusCode,
        ];

        $span->setSuccessfulIf($statusCode >= 100 && $statusCode < 500);

        $this->spanTracer->endSpan($span);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $span = $this->spanPool[$this->getRequestId($event->getRequest())] ?? null;

        if ($span === null) {
            return;
        }

        $span->setError($event->getThrowable());
    }

    protected function getRequestId(Request $request): int
    {
        return \spl_object_id($request);
    }

    protected function getRouteName(Request $request): string
    {
        $route = $request->attributes->get('_route');

        if ($route instanceof Route) {
            return $route->getPath();
        }

        if (\is_string($route) && $route !== '') {
            return $route;
        }

        $route = $request->attributes->get('_controller');

        if (\is_string($route) && $route !== '') {
            return $route;
        }

        return \is_string($route) && $route !== '' ? $route : SpanBundle::UNKNOWN;
    }

    public function reset(): void
    {
        $this->spanPool = [];
    }
}
