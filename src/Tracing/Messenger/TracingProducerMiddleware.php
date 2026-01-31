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
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

class TracingProducerMiddleware implements MiddlewareInterface
{
    use SpanTracerAwareTrait;

    private ?AmqpTransportMetadataRegistry $amqpMetadataRegistry;

    private ?DoctrineTransportMetadataRegistry $doctrineMetadataRegistry;

    public function __construct(
        SpanTracer $spanTracer,
        ?AmqpTransportMetadataRegistry $amqpMetadataRegistry = null,
        ?DoctrineTransportMetadataRegistry $doctrineMetadataRegistry = null
    ) {
        $this->spanTracer = $spanTracer;

        $this->amqpMetadataRegistry = $amqpMetadataRegistry;
        $this->doctrineMetadataRegistry = $doctrineMetadataRegistry;
    }

    /**
     * @throws \Throwable
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if ($envelope->last(ReceivedStamp::class) !== null) {
            return $stack->next()->handle($envelope, $stack);
        }

        if (!$this->spanTracer->hasActiveTrace()) {
            return $stack->next()->handle($envelope, $stack);
        }

        $span = new Span(SpanBundle::UNKNOWN, SpanBundle::SPAN_TYPE_PRODUCER, SpanBundle::SPAN_SUBTYPE_MESSENGER);

        $this->spanTracer->startSpan($span);

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $throwable) {
            $span->setError($throwable);

            throw $throwable;
        } finally {
            $messageName = \get_class($message);

            $transportType = $this->getTransportTypeFromEnvelope($envelope);
            $transportName = $this->getTransportNameFromEnvelope($envelope);

            if ($transportType === SpanBundle::SPAN_SUBTYPE_RABBITMQ) {
                $this->fillAmqpSpan($span, $messageName, $transportType, $transportName);
            } elseif ($transportType === SpanBundle::SPAN_SUBTYPE_DOCTRINE) {
                $this->fillDoctrineSpan($span, $messageName, $transportType, $transportName);
            } else {
                $this->fillDefaultSpan($span, $messageName, $transportType, $transportName, $transportName);
            }

            $this->spanTracer->endSpan($span);
        }

        return $envelope;
    }

    private function getTransportTypeFromEnvelope(Envelope $envelope): string
    {
        /** @var SentStamp|null $stamp */
        $stamp = $envelope->last(SentStamp::class);

        if ($stamp === null) {
            return SpanBundle::SPAN_SUBTYPE_MESSENGER;
        }

        switch ($stamp->getSenderClass()) {
            case 'Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpSender':
            case 'Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport':
                return SpanBundle::SPAN_SUBTYPE_RABBITMQ;
            case 'Symfony\Component\Messenger\Bridge\Redis\Transport\RedisSender':
            case 'Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport':
                return SpanBundle::SPAN_SUBTYPE_REDIS;
            case 'Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineSender':
            case 'Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport':
                return SpanBundle::SPAN_SUBTYPE_DOCTRINE;
            default:
                return SpanBundle::SPAN_SUBTYPE_MESSENGER;
        }
    }

    private function getTransportNameFromEnvelope(Envelope $envelope): string
    {
        /** @var SentStamp|null $stamp */
        $stamp = $envelope->last(SentStamp::class);

        if ($stamp !== null && $stamp->getSenderAlias() !== null) {
            return $stamp->getSenderAlias();
        }

        return SpanBundle::UNKNOWN;
    }

    private function fillAmqpSpan(Span $span, string $messageName, string $transportType, string $transportName): void
    {
        if ($this->amqpMetadataRegistry !== null) {
            $metadata = $this->amqpMetadataRegistry->get($transportName);

            if ($metadata !== null) {
                if ($metadata->exchangeName !== null) {
                    $transportName = $metadata->exchangeName;
                }

                $span->context->server = [
                    'host' => $metadata->host,
                    'port' => $metadata->port,
                ];
            }
        }

        $this->fillDefaultSpan($span, $messageName, $transportType, $transportName, $transportName);
    }

    private function fillDoctrineSpan(Span $span, string $messageName, string $transportType, string $transportName): void
    {
        if ($this->doctrineMetadataRegistry !== null) {
            $metadata = $this->doctrineMetadataRegistry->get($transportName);

            if ($metadata !== null) {
                if ($metadata->databaseType !== null) {
                    $transportType = $metadata->databaseType;
                }

                if ($metadata->databaseName !== null) {
                    $transportName = $metadata->databaseName;
                }

                $queueName = $transportName;

                if ($metadata->tableName !== null && $metadata->queueName !== null) {
                    $queueName = "{$metadata->tableName}/{$metadata->queueName}";
                }

                $span->context->server = [
                    'host' => $metadata->host,
                    'port' => $metadata->port,
                ];

                $this->fillDefaultSpan($span, $messageName, $transportType, $transportName, $queueName);

                return;
            }
        }

        $this->fillDefaultSpan($span, $messageName, $transportType, $transportName, $transportName);
    }

    private function fillDefaultSpan(Span $span, string $messageName, string $transportType, string $transportName, string $queueName): void
    {
        $span->setName("PRODUCE to {$queueName}");

        $span->setSubtype($transportType);

        $span->context->target = [
            'type' => $transportType,
            'name' => $transportName,
        ];

        $span->context->message = [
            'name' => $messageName,
            'queue_name' => $queueName,
        ];
    }
}
