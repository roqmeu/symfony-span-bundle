<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span;

class TracingProducer extends Producer
{
    use RabbitMqTracingTrait;

    /**
     * @param string|mixed $msgBody
     * @param string|mixed $routingKey
     * @param array|mixed $additionalProperties
     *
     * @throws \Throwable
     */
    public function publish($msgBody, $routingKey = null, $additionalProperties = [], ?array $headers = null): void
    {
        if (!$this->spanTracer->hasActiveTrace()) {
            parent::publish($msgBody, $routingKey, $additionalProperties, $headers);

            return;
        }

        $exchangeName = $this->resolveExchangeName();

        $span = new Span(SpanBundle::SPAN_TYPE_PRODUCER, SpanBundle::SPAN_SUBTYPE_RABBITMQ);

        $span->context->message = [
            'queue_name' => $exchangeName,
        ];

        $this->fillServerContext($span);

        $headers ??= [];

        $this->spanTracer->startSpan($span, static function (string $key, string $value) use (&$headers): void {
            if (!\array_key_exists($key, $headers)) {
                $headers[$key] = $value;
            }
        });

        try {
            parent::publish($msgBody, $routingKey, $additionalProperties, $headers);
        } catch (\Throwable $throwable) {
            $span->setError($throwable);

            throw $throwable;
        } finally {
            $this->spanTracer->endSpan($span);
        }
    }

    private function resolveExchangeName(): string
    {
        $exchangeName = $this->exchangeOptions['name'] ?? null;

        return \is_string($exchangeName) && $exchangeName !== '' ? $exchangeName : SpanBundle::UNKNOWN;
    }
}
