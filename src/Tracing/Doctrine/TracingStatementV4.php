<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

class TracingStatementV4 extends AbstractTracingStatement implements Statement
{
    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->statement->bindValue($param, $value, $type);
    }

    /**
     * @throws \Throwable
     */
    public function execute(): Result
    {
        /** @var Result $result */
        $result = $this->traceExecute(function (): Result {
            return $this->statement->execute();
        });

        return $result;
    }
}
