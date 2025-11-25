<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger\Amqp;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Decorator for AmqpTransportFactory that extracts AMQP metadata.
 *
 * Captures exchange_name during transport creation
 * and stores them in AmqpTransportMetadataRegistry for later use in tracing.
 *
 * @implements TransportFactoryInterface<TransportInterface>
 */
class AmqpTransportFactoryDecorator implements TransportFactoryInterface
{
    /**
     * @var TransportFactoryInterface<TransportInterface>
     */
    private TransportFactoryInterface $innerFactory;

    private AmqpTransportMetadataRegistry $metadataRegistry;

    /**
     * @param TransportFactoryInterface<TransportInterface> $innerFactory
     */
    public function __construct(TransportFactoryInterface $innerFactory, AmqpTransportMetadataRegistry $metadataRegistry)
    {
        $this->innerFactory = $innerFactory;
        $this->metadataRegistry = $metadataRegistry;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $transportName = $options['transport_name'] ?? null;

        if ($transportName !== null) {
            $this->extractAndRegisterMetadata($dsn, $options, $transportName);
        }

        return $this->innerFactory->createTransport($dsn, $options, $serializer);
    }

    public function supports(string $dsn, array $options): bool
    {
        return $this->innerFactory->supports($dsn, $options);
    }

    private function extractAndRegisterMetadata(string $dsn, array $options, string $transportName): void
    {
        try {
            $configuration = $this->parseConfiguration($dsn, $options);

            $host = $configuration['host'] ?? null;
            $port = $configuration['port'] ?? null;
            $exchangeName = $configuration['exchange']['name'] ?? null;

            if (\is_numeric($port)) {
                $port = (int) $port;
            }

            $metadata = new AmqpTransportMetadata($transportName, $host, $port, $exchangeName);

            $this->metadataRegistry->register($transportName, $metadata);
        } catch (\Throwable $e) {
            // Silently ignore errors to not break transport creation
        }
    }

    /**
     * Parse AMQP configuration from DSN and options.
     *
     * Simplified version of Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection::fromDsn
     *
     * @return array{host?: mixed, port?: mixed, exchange?: mixed}
     */
    private function parseConfiguration(string $dsn, array $options): array
    {
        $params = \parse_url($dsn);

        if (!\is_array($params) || $params === []) {
            return [];
        }

        $dsnHost = $params['host'] ?? null;
        $dsnPort = $params['port'] ?? null;
        $dsnPath = $params['path'] ?? null;
        $dsnQuery = $params['query'] ?? null;

        $configuration = [
            'host' => null,
            'port' => null,
            'exchange' => ['name' => 'messages'],
        ];

        if (\is_string($dsnHost) && $dsnHost !== '') {
            $configuration['host'] = $dsnHost;
        }

        if (\is_numeric($dsnPort)) {
            $configuration['port'] = $dsnPort;
        }

        if (\is_string($dsnPath) && $dsnPath !== '') {
            $exchangeName = (\explode('/', \trim($dsnPath, '/')))[1] ?? null;

            if (\is_string($exchangeName) && $exchangeName !== '') {
                $configuration['exchange']['name'] = $exchangeName;
            }
        }

        $query = [];

        if (\is_string($dsnQuery) && $dsnQuery !== '') {
            \parse_str($dsnQuery, $query);
        }

        return \array_replace_recursive($configuration, $options, $query);
    }
}
