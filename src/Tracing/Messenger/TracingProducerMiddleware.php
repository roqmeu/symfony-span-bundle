<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Tracing\SpanTracingTrait;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

class TracingProducerMiddleware implements MiddlewareInterface
{
    use SpanTracingTrait;

    public function __construct(
        Dispatcher $dispatcher,
        TransactionPool $tracePool
    ) {
        $this->dispatcher = $dispatcher;
        $this->tracePool = $tracePool;
    }

    /**
     * @throws \Throwable
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(ReceivedStamp::class) !== null) {
            return $stack->next()->handle($envelope, $stack);
        }

        $parent = $this->tracePool->getCurrentSpan();

        if ($parent === null) {
            return $stack->next()->handle($envelope, $stack);
        }

        $span = $this->beginCurrentSpan(
            $parent,
            SpanBundle::UNKNOWN,
            SpanBundle::SPAN_TYPE_PRODUCER,
            SpanBundle::SPAN_SUBTYPE_MESSENGER
        );

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $throwable) {
            $this->errorSpan($span, $throwable);

            throw $throwable;
        } finally {
            $transportName = $this->getTransportNameFromEnvelope($envelope);
            $transportType = $this->getTransportTypeFromEnvelope($envelope);

            $span->name = "PRODUCE {$transportName}";
            $span->subtype = $transportType;

            $span->context->target = [
                'type' => SpanBundle::SPAN_SUBTYPE_MESSENGER,
                'name' => $transportName
            ];

            $this->endSpan($span);
        }

        return $envelope;
    }

    private function getTransportNameFromEnvelope(Envelope $envelope): string
    {
        /** @var SentStamp|null $stamp */
        $stamp = $envelope->last(SentStamp::class);

        if ($stamp !== null && $stamp->getSenderAlias() !== null) {
            return $stamp->getSenderAlias();
        }

        return SpanBundle::SPAN_SUBTYPE_MESSENGER;
    }

    private function getTransportTypeFromEnvelope(Envelope $envelope): string
    {
        /** @var SentStamp|null $stamp */
        $stamp = $envelope->last(SentStamp::class);

        if ($stamp === null) {
            return SpanBundle::SPAN_SUBTYPE_MESSENGER;
        }

        $class = $stamp->getSenderClass();

        if ($class === 'Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpSender') {
            return SpanBundle::SPAN_SUBTYPE_RABBITMQ;
        }

        if ($class === 'Symfony\Component\Messenger\Bridge\Redis\Transport\RedisSender') {
            return SpanBundle::SPAN_SUBTYPE_REDIS;
        }

        if ($class === 'Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineSender') {
            return SpanBundle::SPAN_SUBTYPE_DOCTRINE;
        }

        return SpanBundle::SPAN_SUBTYPE_MESSENGER;
    }
}
