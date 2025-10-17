<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger;

use Symfony\Component\Messenger\Exception\HandlerFailedException;

class TracingConsumerMiddlewareV6 extends TracingConsumerMiddleware
{
    protected function getErrorsFromHandlerError(HandlerFailedException $error): array
    {
        return $error->getWrappedExceptions();
    }
}
