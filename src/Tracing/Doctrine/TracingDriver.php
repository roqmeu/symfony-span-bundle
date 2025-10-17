<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;

class TracingDriver implements Driver
{
    private Driver $driver;
    private Dispatcher $dispatcher;
    private TransactionPool $tracePool;

    public function __construct(
        Dispatcher $dispatcher,
        TransactionPool $tracePool,
        Driver $driver
    ) {
        $this->dispatcher = $dispatcher;
        $this->tracePool = $tracePool;

        $this->driver = $driver;
    }

    public function connect(array $params): Connection
    {
        $connection = $this->driver->connect($params);

        $driverType = $this->determineDriverType();

        return new TracingConnection(
            $this->dispatcher,
            $this->tracePool,
            $connection,
            $params,
            $driverType
        );
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform();
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->driver->getExceptionConverter();
    }

    public function getSchemaManager(
        \Doctrine\DBAL\Connection $conn,
        AbstractPlatform $platform
    ): AbstractSchemaManager {
        return $this->driver->getSchemaManager($conn, $platform);
    }

    private function determineDriverType(): string
    {
        $platform = $this->driver->getDatabasePlatform();

        // TODO getName deprecated - Identify platforms by their class.
        $platformName = $platform->getName();

        $platformMap = [
            'postgresql' => 'postgresql',
            'mysql' => 'mysql',
            'mariadb' => 'mysql',
            'sqlite' => 'sqlite',
            'mssql' => 'mssql',
            'oracle' => 'oracle',
        ];

        return $platformMap[$platformName] ?? 'doctrine';
    }
}
