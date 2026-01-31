<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use PhpAmqpLib\Message\AMQPMessage;
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

        $span = new Span("CONSUME from {$queueName}", SpanBundle::SPAN_TYPE_CONSUMER, SpanBundle::SPAN_SUBTYPE_RABBITMQ);

        $span->context->target = [
            'type' => SpanBundle::SPAN_SUBTYPE_RABBITMQ,
            'name' => $queueName,
        ];
        $span->context->message = [
            'consumer_name' => $this->resolveConsumerName($callback),
            'name' => SpanBundle::UNKNOWN,
            'queue_name' => $queueName,
        ];

        $this->fillServerContext($span);

        $this->spanTracer->startSpanWithTrace($span);

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
