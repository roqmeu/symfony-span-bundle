<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use OldSound\RabbitMqBundle\RabbitMq\BatchConsumer;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span;

class TracingBatchConsumer extends BatchConsumer
{
    use RabbitMqTracingTrait;

    /**
     * @param \Closure|callable $callback
     *
     * @return $this
     */
    public function setCallback($callback)
    {
        $wrappedCallback = function (array $messages) use ($callback) {
            $queueName = $this->resolveQueueName($this->queueOptions['name'] ?? null);

            $span = new Span(SpanBundle::SPAN_TYPE_CONSUMER, SpanBundle::SPAN_SUBTYPE_RABBITMQ);

            $span->context->message = [
                'consumer_name' => $this->resolveConsumerName($callback),
                'queue_name' => $queueName,
            ];

            $this->fillServerContext($span);

            $this->spanTracer->startTraceSpan($span);

            try {
                return \call_user_func($callback, $messages);
            } catch (\Throwable $throwable) {
                $span->setError($throwable);

                throw $throwable;
            } finally {
                $this->spanTracer->endSpan($span);
            }
        };

        return parent::setCallback($wrappedCallback);
    }
}
