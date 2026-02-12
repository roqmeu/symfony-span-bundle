<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

class TracingStatementV3 extends AbstractTracingStatement implements Statement
{
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
        /** @var Result $result */
        $result = $this->traceExecute(function () use ($params): Result {
            return $this->statement->execute($params);
        });

        return $result;
    }
}
