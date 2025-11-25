<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\SpanTracerAwareTrait;

class TracingDriver implements Driver
{
    use SpanTracerAwareTrait;

    use DbalTracingTrait;

    private Driver $driver;

    public function __construct(SpanTracer $spanTracer, Driver $driver)
    {
        $this->spanTracer = $spanTracer;
        $this->driver = $driver;
    }

    public function connect(array $params): DriverConnection
    {
        $connection = $this->driver->connect($params);

        $databaseType = $this->determineDatabaseType($this->driver);
        $databaseName = $this->determineDatabaseName($params);

        return new TracingConnection($this->spanTracer, $connection, $params, $databaseType, $databaseName);
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform();
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->driver->getExceptionConverter();
    }

    /**
     * @return AbstractSchemaManager<AbstractPlatform>
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        return $this->driver->getSchemaManager($conn, $platform);
    }
}
