<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Controller;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\SpanPool;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Tracing\TransactionTracingTrait;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;

class TracingRequestListener implements EventSubscriberInterface
{
    use TransactionTracingTrait;

    public function __construct(
        Dispatcher $dispatcher,
        SpanPool $spanPool,
        TransactionPool $tracePool
    ) {
        $this->dispatcher = $dispatcher;
        $this->spanPool = $spanPool;
        $this->tracePool = $tracePool;
    }

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
        $route = $this->getRouteName($request);

        $span = $this->transactionStart(
            $this->getSpanId($request),
            "{$request->getMethod()} $route",
            SpanBundle::TRANSACTION_TYPE_SERVER,
            SpanBundle::SPAN_SUBTYPE_HTTP,
            $event->isMainRequest()
        );

        $span->context->http_request = [
            'method' => $request->getMethod(),
            'route' => $route,
            'url' => [
                'scheme' => $request->getScheme(),
                'domain' => $request->getHost(),
                'port' => (string)$request->getPort(),
                'path' => $request->getBaseUrl() . $request->getPathInfo(),
            ],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $id = $this->getSpanId($event->getRequest());
        $span = $this->getTransaction($id);

        if ($span === null) {
            return;
        }

        $statusCode = $event->getResponse()->getStatusCode();
        $span->context->http_response = [
            'status_code' => $statusCode,
        ];

        if ($span->successful === null) {
            $span->successful = $statusCode >= 100 && $statusCode < 500;
        }

        $this->transactionEnd($id);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $this->transactionError($this->getSpanId($event->getRequest()), $event->getThrowable());
    }

    private function getSpanId(Request $request): int
    {
        return spl_object_id($request);
    }

    private function getRouteName(Request $request): string
    {
        $route = $request->attributes->get('_route');

        if ($route instanceof Route) {
            return $route->getPath();
        }

        $route = $request->attributes->get('_controller');

        if (is_string($route) && $route !== '') {
            return $route;
        }

        return is_string($route) && $route !== '' ? $route : SpanBundle::UNKNOWN;
    }
}
