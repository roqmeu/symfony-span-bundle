<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Messenger\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Roqmeu\SpanBundle\Tracing\Doctrine\DbalTracingTrait;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Decorator for DoctrineTransportFactory that extracts database metadata.
 *
 * Captures db_type, db_name, table_name during transport creation
 * and stores them in DoctrineTransportMetadataRegistry for later use in tracing.
 *
 * @implements TransportFactoryInterface<TransportInterface>
 */
class DoctrineTransportFactoryDecorator implements TransportFactoryInterface
{
    use DbalTracingTrait;

    /**
     * @var TransportFactoryInterface<TransportInterface>
     */
    private TransportFactoryInterface $innerFactory;

    private DoctrineTransportMetadataRegistry $metadataRegistry;

    private ConnectionRegistry $connectionRegistry;

    /**
     * @param TransportFactoryInterface<TransportInterface> $innerFactory
     */
    public function __construct(
        TransportFactoryInterface $innerFactory,
        DoctrineTransportMetadataRegistry $metadataRegistry,
        ConnectionRegistry $connectionRegistry
    ) {
        $this->innerFactory = $innerFactory;
        $this->metadataRegistry = $metadataRegistry;
        $this->connectionRegistry = $connectionRegistry;
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

            $host = null;
            $port = null;
            $databaseType = null;
            $databaseName = null;
            $tableName = $configuration['table_name'] ?? null;
            $queueName = $configuration['queue_name'] ?? null;

            $connectionName = $configuration['connection'] ?? null;

            if (\is_string($connectionName) && $connectionName !== '') {
                $dbalConnection = $this->connectionRegistry->getConnection($connectionName);

                if ($dbalConnection instanceof Connection) {
                    $connectionParams = $dbalConnection->getParams();

                    $host = $this->determineHost($connectionParams);
                    $port = $this->determinePort($connectionParams);
                    $databaseType = $this->determineDatabaseType($dbalConnection->getDriver());
                    $databaseName = $this->determineDatabaseName($connectionParams);
                }
            }

            $metadata = new DoctrineTransportMetadata($transportName, $host, $port, $databaseType, $databaseName, $tableName, $queueName);

            $this->metadataRegistry->register($transportName, $metadata);
        } catch (\Throwable $e) {
            // Silently ignore errors to not break transport creation
        }
    }

    /**
     * Build configuration from DSN and options.
     *
     * Simplified version of Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection::buildConfiguration
     *
     * @return array{connection?: mixed, table_name?: mixed, queue_name?: mixed}
     */
    private function parseConfiguration(string $dsn, array $options): array
    {
        $params = \parse_url($dsn);

        if (!\is_array($params) || $params === []) {
            return [];
        }

        $dsnHost = $params['host'] ?? null;
        $dsnQuery = $params['query'] ?? null;

        $configuration = [
            'connection' => null,
            'table_name' => 'messenger_messages',
            'queue_name' => 'default',
        ];

        if (\is_string($dsnHost) && $dsnHost !== '') {
            $configuration['connection'] = $dsnHost;
        }

        $query = [];

        if (\is_string($dsnQuery) && $dsnQuery !== '') {
            \parse_str($dsnQuery, $query);
        }

        return \array_replace_recursive($configuration, $options, $query);
    }
}
