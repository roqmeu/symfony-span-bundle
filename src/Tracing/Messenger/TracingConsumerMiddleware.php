<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\SpanPool;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Tracing\TransactionTracingTrait;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

abstract class TracingConsumerMiddleware implements MiddlewareInterface
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

    /**
     * @throws \Throwable
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $messageId = spl_object_id($message);

        $span = $this->transactionStart(
            $messageId,
            SpanBundle::UNKNOWN,
            SpanBundle::TRANSACTION_TYPE_CONSUMER,
            SpanBundle::SPAN_SUBTYPE_MESSENGER,
            true
        );

        $handlerName = null;

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $error) {
            $errors = $this->getErrorsFromHandlerError($error);

            $handlerName = array_key_first($errors);
            var_dump('CONSUME error $handlerName = ' . $handlerName);

            $this->transactionError($messageId, $errors[$handlerName]);

            throw $error;
        } catch (\Throwable $throwable) {
            $this->transactionError($messageId, $throwable);

            throw $throwable;
        } finally {
            /** @var HandledStamp|null $stamp */
            $stamp = $envelope->last(HandledStamp::class);
            if ($stamp !== null) {
                $handlerName = $stamp->getHandlerName();
            }

            $messageName = get_class($message);
            $transportName = $this->getTransportNameFromEnvelope($envelope);
            $transportType = $this->getTransportTypeFromEnvelope($envelope);

            $span->name = "CONSUME {$transportName}";
            $span->subtype = $transportType;

            var_dump('CONSUME $messageName = ' . $messageName);
            var_dump('CONSUME $handlerName = ' . $handlerName);
            var_dump('CONSUME $transportName = ' . $transportName);
            var_dump('CONSUME $transportType = ' . $transportType);

            $this->transactionEnd($messageId);
        }

        return $envelope;
    }

    /**
     * @see TracingConsumerMiddlewareV5::getErrorsFromHandlerError()
     * @see TracingConsumerMiddlewareV6::getErrorsFromHandlerError()
     *
     * @return array<string, \Throwable>
     */
    abstract protected function getErrorsFromHandlerError(HandlerFailedException $error): array;

    private function getTransportNameFromEnvelope(Envelope $envelope): string
    {
        /** @var ReceivedStamp|null $stamp */
        $stamp = $envelope->last(ReceivedStamp::class);

        return $stamp !== null ? $stamp->getTransportName() : SpanBundle::SPAN_SUBTYPE_MESSENGER;
    }

    private function getTransportTypeFromEnvelope(Envelope $envelope): string
    {
        if ($envelope->last('Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp') !== null) {
            return SpanBundle::SPAN_SUBTYPE_RABBITMQ;
        }

        if ($envelope->last('Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceivedStamp') !== null) {
            return SpanBundle::SPAN_SUBTYPE_REDIS;
        }

        if ($envelope->last('Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceivedStamp') !== null) {
            return SpanBundle::SPAN_SUBTYPE_DOCTRINE;
        }

        return SpanBundle::SPAN_SUBTYPE_MESSENGER;
    }
}

