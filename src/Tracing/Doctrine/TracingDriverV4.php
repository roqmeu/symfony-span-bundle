<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;

class TracingDriverV4 extends AbstractTracingDriver implements Driver
{
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform($versionProvider);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->driver->getExceptionConverter();
    }

    protected function resolveDatabasePlatform(DriverConnection $connection): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform($connection);
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function createTracingConnection(DriverConnection $connection, array $params, string $databaseType, string $databaseName): DriverConnection
    {
        return new TracingConnectionV4($this->spanTracer, $connection, $params, $databaseType, $databaseName);
    }
}
