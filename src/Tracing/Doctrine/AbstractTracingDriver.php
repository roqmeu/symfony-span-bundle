<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;

abstract class AbstractTracingDriver extends AbstractTracingDbal
{
    use SpanTracerAwareTrait;

    protected Driver $driver;

    public function __construct(SpanTracer $spanTracer, Driver $driver)
    {
        $this->spanTracer = $spanTracer;
        $this->driver = $driver;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function connect(array $params): DriverConnection
    {
        $connection = $this->driver->connect($params);

        $platform = $this->resolveDatabasePlatform($connection);

        $databaseType = $this->determineDatabaseTypeFromPlatform($platform);
        $databaseName = $this->determineDatabaseName($params);

        return $this->createTracingConnection($connection, $params, $databaseType, $databaseName);
    }

    abstract protected function resolveDatabasePlatform(DriverConnection $connection): AbstractPlatform;

    /**
     * @param array<string, mixed> $params
     */
    abstract protected function createTracingConnection(
        DriverConnection $connection,
        array $params,
        string $databaseType,
        string $databaseName
    ): DriverConnection;
}
