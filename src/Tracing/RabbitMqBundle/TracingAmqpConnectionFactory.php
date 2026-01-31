<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use OldSound\RabbitMqBundle\Provider\ConnectionParametersProviderInterface;
use OldSound\RabbitMqBundle\RabbitMq\AMQPConnectionFactory;

class TracingAmqpConnectionFactory extends AMQPConnectionFactory
{
    private ?array $resolvedParameters;

    public function __construct($class, array $parameters, ?ConnectionParametersProviderInterface $parametersProvider = null)
    {
        parent::__construct($class, $parameters, $parametersProvider);

        $this->resolvedParameters = $this->resolveParameters($this->normalizeParameters($parameters, $parametersProvider));
    }

    public function registerConnectionMetadata(?string $connectionName = null, ?RabbitMqConnectionMetadataRegistry $registry = null): void
    {
        if ($this->resolvedParameters === null || $connectionName === null || $connectionName === '' || $registry === null) {
            return;
        }

        $registry->register(
            $connectionName,
            new RabbitMqConnectionMetadata(
                $connectionName,
                $this->normalizeHost($this->resolvedParameters['host'] ?? null),
                $this->normalizePort($this->resolvedParameters['port'] ?? null),
            )
        );

        $this->resolvedParameters = null;
    }

    private function normalizeParameters(array $parameters, ?ConnectionParametersProviderInterface $parametersProvider): array
    {
        $parameters = $this->parseUrl($parameters);

        foreach ($parameters['hosts'] as $key => $hostParameters) {
            if (isset($hostParameters['url'])) {
                $parameters['hosts'][$key] = $this->parseUrl($hostParameters);
            }
        }

        if ($parametersProvider !== null) {
            $parameters = \array_replace($parameters, $parametersProvider->getConnectionParameters());
        }

        return $parameters;
    }

    private function parseUrl(array $parameters): array
    {
        $url = $parameters['url'] ?? null;

        if (!\is_string($url) || $url === '') {
            return $parameters;
        }

        $urlParams = \parse_url($url);

        if (!\is_array($urlParams) || $urlParams === []) {
            return $parameters;
        }

        $urlHost = $urlParams['host'] ?? null;
        $urlPort = $urlParams['port'] ?? null;
        $urlQuery = $urlParams['query'] ?? null;

        if (\is_string($urlHost) && $urlHost !== '') {
            $parameters['host'] = $urlHost;
        }

        if (\is_numeric($urlPort)) {
            $parameters['port'] = (int)$urlPort;
        }

        if (\is_string($urlQuery) && $urlQuery !== '') {
            \parse_str($urlQuery, $parameters);
        }

        return $parameters;
    }

    private function resolveParameters(array $config): array
    {
        if (!isset($config['hosts']) || !\is_array($config['hosts']) || $config['hosts'] === []) {
            return $config;
        }

        $first = \reset($config['hosts']);

        if (\is_array($first)) {
            return \array_replace($config, $first);
        }

        return $config;
    }

    /**
     * @param string|mixed $host
     */
    private function normalizeHost($host): ?string
    {
        return \is_string($host) && $host !== '' ? $host : null;
    }

    /**
     * @param int|mixed $port
     */
    private function normalizePort($port): ?int
    {
        return \is_numeric($port) ? (int)$port : null;
    }
}
