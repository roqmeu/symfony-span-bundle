<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;
use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\Tracing\Messenger\Amqp\AmqpTransportMetadataRegistry;
use Roqmeu\SpanBundle\Tracing\Messenger\Doctrine\DoctrineTransportMetadataRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

abstract class TracingConsumerMiddleware implements MiddlewareInterface
{
    use SpanTracerAwareTrait;

    private HandlersLocatorInterface $handlersLocator;

    private ?AmqpTransportMetadataRegistry $amqpMetadataRegistry;

    private ?DoctrineTransportMetadataRegistry $doctrineMetadataRegistry;

    public function __construct(
        SpanTracer $spanTracer,
        HandlersLocatorInterface $handlersLocator,
        ?AmqpTransportMetadataRegistry $amqpMetadataRegistry = null,
        ?DoctrineTransportMetadataRegistry $doctrineMetadataRegistry = null
    ) {
        $this->spanTracer = $spanTracer;
        $this->handlersLocator = $handlersLocator;

        $this->amqpMetadataRegistry = $amqpMetadataRegistry;
        $this->doctrineMetadataRegistry = $doctrineMetadataRegistry;
    }

    /**
     * @throws \Throwable
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        $span = new Span(SpanBundle::UNKNOWN, SpanBundle::SPAN_TYPE_CONSUMER, SpanBundle::SPAN_SUBTYPE_MESSENGER);

        $handlerName = null;

        $this->spanTracer->startSpanWithTrace($span);

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $error) {
            $errors = $this->getErrorsFromHandlerError($error);

            $firstErrorKey = \array_key_first($errors);
            $firstError = $errors[$firstErrorKey];

            if (\is_string($firstErrorKey) && $firstErrorKey !== '') {
                $handlerName = $firstErrorKey;
            }

            $span->setError($firstError);

            throw $error;
        } catch (\Throwable $throwable) {
            $span->setError($throwable);

            throw $throwable;
        } finally {
            $messageName = \get_class($message);

            /** @var HandledStamp|null $stamp */
            $stamp = $envelope->last(HandledStamp::class);

            if ($stamp !== null) {
                $handlerName = $stamp->getHandlerName();
            }

            if ($handlerName === null) {
                foreach ($this->handlersLocator->getHandlers($envelope) as $handlerCandidate) {
                    if ($handlerCandidate instanceof HandlerDescriptor) {
                        $handlerName = $handlerCandidate->getName();

                        break;
                    }
                }
            }

            $transportType = $this->getTransportTypeFromEnvelope($envelope);
            $transportName = $this->getTransportNameFromEnvelope($envelope);

            if ($transportType === SpanBundle::SPAN_SUBTYPE_RABBITMQ) {
                $this->fillAmqpSpan($span, $envelope, $handlerName, $messageName, $transportType, $transportName);
            } elseif ($transportType === SpanBundle::SPAN_SUBTYPE_DOCTRINE) {
                $this->fillDoctrineSpan($span, $envelope, $handlerName, $messageName, $transportType, $transportName);
            } else {
                $this->fillDefaultSpan($span, $envelope, $handlerName, $messageName, $transportType, $transportName);
            }

            $this->spanTracer->endSpan($span);
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

    private function getTransportNameFromEnvelope(Envelope $envelope): string
    {
        /** @var ReceivedStamp|null $stamp */
        $stamp = $envelope->last(ReceivedStamp::class);

        return $stamp !== null ? $stamp->getTransportName() : SpanBundle::UNKNOWN;
    }

    private function fillAmqpSpan(
        Span $span,
        Envelope $envelope,
        ?string $handlerName,
        ?string $messageName,
        string $transportType,
        string $transportName
    ): void {
        if ($this->amqpMetadataRegistry !== null) {
            $metadata = $this->amqpMetadataRegistry->get($transportName);

            if ($metadata !== null) {
                $span->context->server = [
                    'host' => $metadata->host,
                    'port' => $metadata->port,
                ];
            }
        }

        $stamp = $envelope->last('Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp');

        if ($stamp !== null) {
            $transportName = $stamp->getQueueName();
        }

        $this->fillDefaultSpan($span, $envelope, $handlerName, $messageName, $transportType, $transportName);
    }

    private function fillDoctrineSpan(
        Span $span,
        Envelope $envelope,
        ?string $handlerName,
        ?string $messageName,
        string $transportType,
        string $transportName
    ): void {
        if ($this->doctrineMetadataRegistry !== null) {
            $metadata = $this->doctrineMetadataRegistry->get($transportName);

            if ($metadata !== null) {
                if ($metadata->databaseType !== null) {
                    $transportType = $metadata->databaseType;
                }

                if ($metadata->databaseName !== null) {
                    $transportName = $metadata->databaseName;
                }

                $queueName = null;

                if ($metadata->tableName !== null && $metadata->queueName !== null) {
                    $queueName = "{$metadata->tableName}/{$metadata->queueName}";
                }

                $span->context->server = [
                    'host' => $metadata->host,
                    'port' => $metadata->port,
                ];

                $this->fillDefaultSpan($span, $envelope, $handlerName, $messageName, $transportType, $transportName);

                if ($queueName !== null) {
                    $span->setName("CONSUME from {$queueName}");
                }

                return;
            }
        }

        $this->fillDefaultSpan($span, $envelope, $handlerName, $messageName, $transportType, $transportName);
    }

    private function fillDefaultSpan(
        Span $span,
        Envelope $envelope,
        ?string $handlerName,
        ?string $messageName,
        string $transportType,
        string $transportName
    ): void {
        $span->setName("CONSUME from {$transportName}");
        $span->setSubtype($transportType);

        $span->context->target = [
            'type' => $transportType,
            'name' => $transportName,
        ];

        $span->context->message = [
            'consumer_name' => $handlerName,
            'name' => $messageName,
            'queue_name' => $transportName,
            'retry_attempt' => RedeliveryStamp::getRetryCountFromEnvelope($envelope),
            'retry_delay' => $this->getRetryDelayFromEnvelope($envelope),
        ];
    }

    private function getRetryDelayFromEnvelope(Envelope $envelope): int
    {
        /** @var DelayStamp|null $stamp */
        $stamp = $envelope->last(DelayStamp::class);

        return $stamp !== null ? $stamp->getDelay() : 0;
    }
}
