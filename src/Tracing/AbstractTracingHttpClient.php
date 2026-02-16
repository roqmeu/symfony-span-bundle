<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing;

use Psr\Http\Message\UriInterface;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\State\Span;

abstract class AbstractTracingHttpClient
{
    protected SpanTracer $spanTracer;

    private const SERVICE_PORTS = [
        'amqp' => 5672,
        'amqps' => 5671,
        'ftp' => 21,
        'ftps' => 990,
        'http' => 80,
        'https' => 443,
        'imap' => 143,
        'imaps' => 993,
        'ldap' => 389,
        'ldaps' => 636,
        'mqtt' => 1883,
        'pop3' => 110,
        'pop3s' => 995,
        'smtp' => 25,
        'smtps' => 465,
        'socks' => 1080,
        'ws' => 80,
        'wss' => 443,
    ];

    protected function __construct(SpanTracer $spanTracer)
    {
        $this->spanTracer = $spanTracer;
    }

    protected function makeRequestSpan(string $method, UriInterface $uri): Span
    {
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        if ($port === null && $scheme !== '') {
            $port = self::SERVICE_PORTS[$scheme] ?? null;
        }

        if ($port === null && $scheme !== '') {
            $schemePort = \getservbyname($scheme, 'tcp');

            if ($schemePort !== false) {
                $port = $schemePort;
            }
        }

        $span = new Span(SpanBundle::SPAN_TYPE_CLIENT, SpanBundle::SPAN_SUBTYPE_HTTP);

        $span->context->http_request = [
            'method' => $method,
            'url' => [
                'domain' => $host,
                'path' => $uri->getPath(),
                'port' => $port,
                'scheme' => $scheme,
            ],
        ];

        return $span;
    }
}
