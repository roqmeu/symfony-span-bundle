<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\Doctrine;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\SpanTracer;
use Roqmeu\SpanBundle\State\Span;

class TracingStatement implements Statement
{
    use DbalTracingTrait;

    private SpanTracer $spanTracer;

    private Statement $statement;

    private string $sql;

    public function __construct(SpanTracer $spanTracer, Statement $statement, string $sql, array $connectionParams, string $databaseType, string $databaseName)
    {
        $this->spanTracer = $spanTracer;
        $this->statement = $statement;
        $this->sql = $sql;

        $this->connectionParams = $connectionParams;
        $this->databaseType = $databaseType;
        $this->databaseName = $databaseName;
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
        if (!$this->spanTracer->hasActiveTrace()) {
            return $this->statement->execute($params);
        }

        $span = new Span($this->buildSpanName($this->sql), SpanBundle::SPAN_TYPE_DB, $this->databaseType);

        $this->fillSpanContext($span, $this->sql);

        $this->spanTracer->startSpan($span);

        try {
            return $this->statement->execute($params);
        } catch (\Throwable $error) {
            $span->setError($error);

            throw $error;
        } finally {
            $this->spanTracer->endSpan($span);
        }
    }
}
