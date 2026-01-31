<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;
use Roqmeu\SpanBundle\State\Span;

trait RabbitMqTracingTrait
{
    use SpanTracerAwareTrait;

    private ?RabbitMqConnectionMetadataRegistry $spanBundleRegistry = null;

    private ?string $spanBundleConnectionName = null;

    public function spanBundleSetRegistry(?RabbitMqConnectionMetadataRegistry $registry = null): void
    {
        $this->spanBundleRegistry = $registry;
    }

    public function spanBundleSetConnectionName(?string $connectionName = null): void
    {
        $this->spanBundleConnectionName = $connectionName;
    }

    protected function fillServerContext(Span $span): void
    {
        if ($this->spanBundleRegistry === null || $this->spanBundleConnectionName === null) {
            return;
        }

        $metadata = $this->spanBundleRegistry->get($this->spanBundleConnectionName);
        if ($metadata === null) {
            return;
        }

        $span->context->server = [
            'host' => $metadata->host,
            'port' => $metadata->port,
        ];
    }

    /**
     * @param string|mixed $queueName
     */
    protected function resolveQueueName($queueName): string
    {
        return \is_string($queueName) && $queueName !== '' ? $queueName : SpanBundle::UNKNOWN;
    }

    /**
     * @param callable|mixed $callback
     */
    protected function resolveConsumerName($callback): ?string
    {
        if (\is_array($callback)) {
            $target = $callback[0] ?? null;
            $method = $callback[1] ?? null;

            if (\is_object($target)) {
                $target = \get_class($target);
            }

            if (\is_string($target)) {
                if (\is_string($method)) {
                    return "{$target}::{$method}";
                }

                return $target;
            }

            return null;
        }

        if (\is_string($callback)) {
            return $callback;
        }

        if (\is_object($callback)) {
            return \get_class($callback);
        }

        return null;
    }
}
