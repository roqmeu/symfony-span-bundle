<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span;

trait RabbitMqConsumerTracingTrait
{
    use RabbitMqTracingTrait;

    /**
     * @param string|mixed $queueName
     * @param callable|mixed $callback
     *
     * @throws \Throwable
     */
    protected function processMessageQueueCallback(AMQPMessage $msg, $queueName, $callback): void
    {
        $queueName = $this->resolveQueueName($queueName);

        $span = new Span(SpanBundle::SPAN_TYPE_CONSUMER, SpanBundle::SPAN_SUBTYPE_RABBITMQ);

        $span->context->message = [
            'consumer_name' => $this->resolveConsumerName($callback),
            'queue_name' => $queueName,
        ];

        $this->fillServerContext($span);

        /** @var AMQPTable $headers */
        $headers = $msg->has('application_headers') ? $msg->get('application_headers') : null;

        $propagationExtractor = null;

        if ($headers instanceof AMQPTable) {
            $propagationExtractor = static function (string $key) use ($headers): ?string {
                return ((string)($headers[$key] ?? null)) ?: null;
            };
        }

        $this->spanTracer->startTraceSpan($span, $propagationExtractor);

        try {
            parent::processMessageQueueCallback($msg, $queueName, $callback);
        } catch (\Throwable $throwable) {
            $span->setError($throwable);

            throw $throwable;
        } finally {
            $this->spanTracer->endSpan($span);
        }
    }
}
