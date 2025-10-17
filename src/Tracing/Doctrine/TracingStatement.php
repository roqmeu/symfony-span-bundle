<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\TransactionPool;
use Roqmeu\SpanBundle\Tracing\SpanTracingTrait;
use Roqmeu\SpanBundle\Transport\Dispatcher\Dispatcher;

class TracingStatement implements Statement
{
    use SpanTracingTrait;
    use DbalTracingTrait;

    private Statement $statement;

    private string $sql;

    public function __construct(
        Dispatcher $dispatcher,
        TransactionPool $tracePool,
        Statement $statement,
        string $sql,
        array $connectionParams,
        string $driverType
    ) {
        $this->statement = $statement;
        $this->dispatcher = $dispatcher;

        $this->tracePool = $tracePool;
        $this->sql = $sql;

        $this->connectionParams = $connectionParams;
        $this->driverType = $driverType;
        $this->databaseName = $connectionParams['dbname'] ?? $connectionParams['path'] ?? 'unknown';
    }

    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        return $this->statement->bindParam($param, $variable, $type, $length);
    }

    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        return $this->statement->bindValue($param, $value, $type);
    }

    public function execute($params = null): Result
    {
        $parent = $this->tracePool->getCurrentSpan();

        if ($parent === null) {
            return $this->statement->execute($params);
        }

        $spanName = $this->buildSpanName($this->sql);
        $spanSubtype = $this->getSpanSubtype();

        $span = $this->beginSpan(
            $parent,
            $spanName,
            SpanBundle::SPAN_TYPE_DB,
            $spanSubtype
        );

        $this->fillSpanContext($span, $this->sql);

        try {
            return $this->statement->execute($params);
        } catch (\Throwable $error) {
            $this->errorSpan($span, $error);

            throw $error;
        } finally {
            $this->endSpan($span);
        }
    }
}
