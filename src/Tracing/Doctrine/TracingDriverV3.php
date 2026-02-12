<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class TracingDriverV3 extends AbstractTracingDriver implements Driver
{
    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform();
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->driver->getExceptionConverter();
    }

    protected function resolveDatabasePlatform(DriverConnection $connection): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform();
    }

    /**
     * @return AbstractSchemaManager<AbstractPlatform>
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        return $this->driver->getSchemaManager($conn, $platform);
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function createTracingConnection(DriverConnection $connection, array $params, string $databaseType, string $databaseName): DriverConnection
    {
        return new TracingConnectionV3($this->spanTracer, $connection, $params, $databaseType, $databaseName);
    }
}
