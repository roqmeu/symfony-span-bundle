<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Bridge;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span as BundleSpan;
use Roqmeu\SpanBundle\Transport\Event\SpanStartedEvent;
use Roqmeu\SpanBundle\Transport\Event\TraceEndedEvent;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @see https://github.com/elastic/apm/tree/main/specs/agents — Elastic APM agent specifications
 */
class ElasticApmBridge implements ResetInterface
{
    /**
     * @var array<int, TransactionInterface>
     */
    protected array $transactions = [];

    /**
     * @var array<int, SpanInterface>
     */
    protected array $spans = [];

    protected bool $enabled;

    protected bool $isUseSpanCompression;

    public function __construct(bool $enabled = false, bool $isUseSpanCompression = false)
    {
        $this->enabled = $enabled && \class_exists('Elastic\Apm\ElasticApm');
        $this->isUseSpanCompression = $isUseSpanCompression;

        if ($this->enabled) {
            $this->discardUnknownTransaction();
        }
    }

    public function reset(): void
    {
        $this->transactions = [];

        $this->spans = [];
    }

    public function onSpanStarted(SpanStartedEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $injector = $event->propagationInjector;
        $extractor = $event->propagationExtractor;

        if ($injector === null && $extractor === null) {
            return;
        }

        $bundleSpan = $event->span;
        $bundleTrace = $bundleSpan->getTrace();
        $bundleTraceSpan = $bundleSpan->getTraceSpan();

        if ($bundleTrace === null || $bundleTraceSpan === null) {
            return;
        }

        $transaction = $this->getTransactionStub($bundleTraceSpan, $extractor);

        if ($transaction === null || $injector === null || $transaction->isNoop()) {
            return;
        }

        if ($this->isUseSpanCompression || !$transaction->isSampled()) {
            $transaction->injectDistributedTracingHeaders($injector);

            return;
        }

        $defaultStartTime = $bundleTraceSpan->getStartTime();

        if ($defaultStartTime === null) {
            return;
        }

        $span = $this->getSpanStubWithParent($transaction, $bundleSpan, $defaultStartTime);

        $span->injectDistributedTracingHeaders($injector);
    }

    public function onTraceEnded(TraceEndedEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->discardUnknownTransaction();

        $bundleTrace = $event->trace;
        $bundleSpan = $bundleTrace->getSpan();

        if ($bundleSpan === null || $bundleSpan->getStartTime() === null) {
            return;
        }

        $defaultStartTime = $bundleSpan->getStartTime();
        $defaultEndTime = $bundleSpan->getEndTime() ?? \microtime(true);

        if ($defaultEndTime < $defaultStartTime) {
            return;
        }

        $transaction = $this->getTransactionStub($bundleSpan);

        if ($transaction === null) {
            return;
        }

        if ($transaction->isNoop()) {
            $this->endSegment($bundleSpan, $transaction, $defaultStartTime, $defaultEndTime);

            unset($this->transactions[\spl_object_id($bundleTrace)]);

            return;
        }

        $this->fillTransaction($bundleSpan, $transaction);

        if (!$transaction->isSampled()) {
            $this->endSegment($bundleSpan, $transaction, $defaultStartTime, $defaultEndTime);

            unset($this->transactions[\spl_object_id($bundleTrace)]);

            return;
        }

        $spanStack = [$bundleSpan];
        $segmentStack = [$transaction];

        foreach ($bundleSpan->iterateChildrenEuler() as $bundleSpanChild) {
            $spanStackEnd = \end($spanStack);
            $segmentStackEnd = \end($segmentStack);

            if ($spanStackEnd === false || $segmentStackEnd === false) {
                continue;
            }

            if ($spanStackEnd !== $bundleSpanChild) {
                $spanStack[] = $bundleSpanChild;
                $segmentStack[] = $this->fillSpan($bundleSpanChild, $this->getSpanStub($segmentStackEnd, $bundleSpanChild, $defaultStartTime));
            } else {
                \array_pop($spanStack);
                \array_pop($segmentStack);

                $this->endSegment($bundleSpanChild, $segmentStackEnd, $defaultStartTime, $defaultEndTime);

                unset($this->spans[\spl_object_id($bundleSpanChild)]);
            }
        }

        $this->endSegment($bundleSpan, $transaction, $defaultStartTime, $defaultEndTime);

        unset($this->transactions[\spl_object_id($bundleTrace)]);
    }

    protected function getTransactionStub(BundleSpan $bundleSpan, ?\Closure $extractor = null): ?TransactionInterface
    {
        $trace = $bundleSpan->getTrace();

        if ($trace === null) {
            return null;
        }

        $transaction = $this->transactions[\spl_object_id($trace)] ?? null;

        if ($transaction !== null) {
            return $transaction;
        }

        if ($bundleSpan->getStartTime() === null) {
            return null;
        }

        $this->discardUnknownTransaction();

        $builder = ElasticApm::newTransaction('unnamed', 'custom');
        $builder->timestamp($this->secondsToMicros($bundleSpan->getStartTime()));

        if ($extractor !== null) {
            $builder->distributedTracingHeaderExtractor($extractor);
        }

        return $this->transactions[\spl_object_id($trace)] = $builder->begin();
    }

    protected function discardUnknownTransaction(): void
    {
        $transaction = ElasticApm::getCurrentTransaction();

        if (!\in_array($transaction, $this->transactions, true)) {
            $transaction->discard();
        }
    }

    protected function getSpanStubWithParent(ExecutionSegmentInterface $segment, BundleSpan $bundleSpan, float $defaultStartTime): SpanInterface
    {
        $stack = [$bundleSpan];
        $bundleSpan = $bundleSpan->getParent();

        while ($bundleSpan !== null) {
            $bundleSpanParent = $bundleSpan->getParent();

            if ($bundleSpanParent === null) {
                break;
            }

            $segmentParent = $this->spans[\spl_object_id($bundleSpan)] ?? null;

            if ($segmentParent !== null) {
                $segment = $segmentParent;

                break;
            }

            $stack[] = $bundleSpan;
            $bundleSpan = $bundleSpanParent;
        }

        for ($idx = count($stack) - 1; $idx >= 1; $idx--) {
            $segment = $this->getSpanStub($segment, $stack[$idx], $defaultStartTime);
        }

        return $this->getSpanStub($segment, $stack[0], $defaultStartTime);
    }

    protected function getSpanStub(ExecutionSegmentInterface $segment, BundleSpan $bundleSpan, float $defaultStartTime): SpanInterface
    {
        $span = $this->spans[\spl_object_id($bundleSpan)] ?? null;

        if ($span !== null) {
            return $span;
        }

        $span = $segment->beginChildSpan('unnamed', 'custom', null, null, $this->secondsToMicros($bundleSpan->getStartTime() ?? $defaultStartTime));

        if ($this->isUseSpanCompression && \method_exists($span, 'setCompressible')) {
            $span->setCompressible(true);
        }

        return $this->spans[\spl_object_id($bundleSpan)] = $span;
    }

    protected function fillTransaction(BundleSpan $bundleSpan, TransactionInterface $transaction): TransactionInterface
    {
        $transactionName = null;
        $transactionType = null;
        $transactionResult = null;

        if ($bundleSpan->getType() === SpanBundle::SPAN_TYPE_SERVER && $bundleSpan->getSubtype() === SpanBundle::SPAN_SUBTYPE_HTTP) {
            $method = \strtoupper($bundleSpan->context->http_request['method'] ?? '');

            $route = $bundleSpan->context->http_request['route'] ?? '';

            if ($route === '') {
                $route = 'unknown route';
            }

            $transactionName = "{$method} {$route}";

            $statusCode = $bundleSpan->context->http_response['status_code'] ?? null;

            if (\is_int($statusCode) && $statusCode > 0) {
                $statusCode = \intdiv($statusCode, 100);

                if ($statusCode > 0) {
                    $transactionResult = "HTTP {$statusCode}xx";
                }
            }

            $transactionType = 'request';
        } elseif ($bundleSpan->getType() === SpanBundle::SPAN_TYPE_CONSUMER) {
            $framework = $bundleSpan->getSubtype() ?? '';
            $queue = $bundleSpan->context->message['queue_name'] ?? '';

            $transactionName = $this->makeMessagingSegmentName('RECEIVE', 'from', $framework, $queue);

            $transactionType = 'messaging';
        } elseif ($bundleSpan->getType() === SpanBundle::SPAN_TYPE_CONSOLE) {
            $commandName = $bundleSpan->context->command['name'] ?? '';

            if ($commandName !== '') {
                $transactionName = $commandName;
            }

            $transactionType = 'cli';
        }

        if ($transactionName === null || $transactionName === '') {
            $transactionName = 'unnamed';
        }
        if ($transactionType === null) {
            $transactionType = 'custom';
        }

        $transaction->setName($transactionName);
        $transaction->setType($transactionType);
        $transaction->setResult($transactionResult);

        $transaction->setOutcome($bundleSpan->isSuccessful() ? 'success' : 'failure');

        $this->fillTransactionContext($bundleSpan, $transaction);

        $error = $bundleSpan->getError();

        if ($error !== null) {
            $transaction->createErrorFromThrowable($error);
        }

        return $transaction;
    }

    protected function fillTransactionContext(BundleSpan $span, TransactionInterface $elasticTransaction): void
    {
        $context = $elasticTransaction->context();

        if ($span->context->framework !== null) {
            $frameworkDebug = $span->context->framework['debug'] ?? null;
            $frameworkEnvironment = $span->context->framework['environment'] ?? '';
            $frameworkName = $span->context->framework['name'] ?? '';
            $frameworkVersion = $span->context->framework['version'] ?? '';

            if ($frameworkDebug !== null) {
                $context->setLabel('php_framework_debug', $frameworkDebug);
            }
            if ($frameworkEnvironment !== '') {
                $context->setLabel('php_framework_environment', $frameworkEnvironment);
            }
            if ($frameworkName !== '') {
                $context->setLabel('php_framework_name', $frameworkName);
            }
            if ($frameworkVersion !== '') {
                $context->setLabel('php_framework_version', $frameworkVersion);
            }
        }

        if ($span->context->process !== null) {
            $processInteractive = $span->context->process['interactive'] ?? null;
            $runtimeName = $span->context->process['runtime_name'] ?? '';
            $runtimeVersion = $span->context->process['runtime_version'] ?? '';

            if ($processInteractive !== null) {
                $context->setLabel('php_process_interactive', $processInteractive);
            }
            if ($runtimeName !== '') {
                $context->setLabel('php_runtime_name', $runtimeName);
            }
            if ($runtimeVersion !== '') {
                $context->setLabel('php_runtime_version', $runtimeVersion);
            }
        }

        if ($span->context->command !== null) {
            $commandName = $span->context->command['name'] ?? '';

            $context->setLabel('php_command_name', $commandName);
        }

        if ($span->context->http_request !== null) {
            $requestMethod = $span->context->http_request['method'] ?? '';

            $request = $context->request();

            if ($requestMethod !== '') {
                $request->setMethod($requestMethod);
            }

            $requestUrl = $span->context->http_request['url'] ?? null;

            if (\is_array($requestUrl)) {
                $url = $request->url();

                $requestUrlDomain = $requestUrl['domain'] ?? '';
                $requestUrlPath = $requestUrl['path'] ?? '';
                $requestUrlPort = $requestUrl['port'] ?? 0;
                $requestUrlScheme = $requestUrl['scheme'] ?? '';

                if ($requestUrlDomain !== '') {
                    $url->setDomain($requestUrlDomain);
                }
                if ($requestUrlPath !== '') {
                    $url->setPath($requestUrlPath);
                }
                if ($requestUrlPort > 0) {
                    $url->setPort($requestUrlPort);
                }
                if ($requestUrlScheme !== '') {
                    $url->setProtocol($requestUrlScheme);
                }

                if ($requestUrlScheme !== '' && $requestUrlDomain !== '') {
                    $full = $requestUrlScheme . '://' . $requestUrlDomain . ($requestUrlPort > 0 ? ':' . $requestUrlPort : '') . $requestUrlPath;

                    $url->setFull($full);
                    $url->setOriginal($full);
                }
            }
        }

        if ($span->context->message !== null) {
            $messageConsumerName = $span->context->message['consumer_name'] ?? '';
            $messageName = $span->context->message['name'] ?? '';
            $messageQueueName = $span->context->message['queue_name'] ?? '';
            $messageRetryAttempt = $span->context->message['retry_attempt'] ?? 0;
            $messageRetryDelay = $span->context->message['retry_delay'] ?? 0;

            if ($messageConsumerName !== '') {
                $context->setLabel('message_consumer_name', $messageConsumerName);
            }
            if ($messageName !== '') {
                $context->setLabel('message_name', $messageName);
            }
            if ($messageQueueName !== '') {
                $context->setLabel('message_queue_name', $messageQueueName);
            }
            if ($messageRetryAttempt > 0) {
                $context->setLabel('message_retry_attempt', $messageRetryAttempt);
            }
            if ($messageRetryDelay > 0) {
                $context->setLabel('message_retry_delay', $messageRetryDelay);
            }
        }
    }

    protected function fillSpan(BundleSpan $bundleSpan, SpanInterface $span): SpanInterface
    {
        $type = $bundleSpan->getType();
        $subtype = $bundleSpan->getSubtype();
        $action = null;

        if ($type === SpanBundle::SPAN_TYPE_DB) {
            $this->fillDbSpan($bundleSpan, $span);

            $type = 'db';
        } elseif ($type === SpanBundle::SPAN_TYPE_CLIENT) {
            if ($subtype === SpanBundle::SPAN_SUBTYPE_HTTP) {
                $this->fillHttpClientSpan($bundleSpan, $span);
            }

            $type = 'external';
        } elseif ($type === SpanBundle::SPAN_TYPE_PRODUCER) {
            $this->fillMessagingSpan($bundleSpan, $span);

            $action = 'send';
            $type = 'messaging';
        } elseif ($type === SpanBundle::SPAN_TYPE_INTERNAL) {
            if ($subtype === SpanBundle::SPAN_SUBTYPE_PROFILE) {
                $this->fillProfilingSpan($bundleSpan, $span);
            }

            $type = 'app';
        }

        if ($type === '') {
            $type = 'custom';
        }

        $span->setType($type);
        $span->setSubtype($subtype ?: null);
        $span->setAction($action ?: null);

        $span->setOutcome($bundleSpan->isSuccessful() ? 'success' : 'failure');

        $error = $bundleSpan->getError();

        if ($error !== null) {
            $span->createErrorFromThrowable($error);
        }

        return $span;
    }

    protected function fillDbSpan(BundleSpan $bundleSpan, SpanInterface $span): void
    {
        $context = $span->context();

        if ($bundleSpan->context->db !== null) {
            $dbStatement = $bundleSpan->context->db['statement'] ?? '';

            if ($dbStatement !== '') {
                $context->db()->setStatement($dbStatement);
            }

            $dbInstance = $bundleSpan->context->db['instance'] ?? '';

            if ($dbStatement !== '' && $dbInstance !== '') {
                $span->setName($this->makeSqlSpanName($dbInstance, $dbStatement));
            }

            $targetType = $bundleSpan->getSubtype();
            $targetName = $dbInstance !== '' ? $dbInstance : null;

            $this->fillSpanTarget($span, $targetType, $targetName);
        }

        if ($bundleSpan->context->server !== null) {
            $serverHost = $bundleSpan->context->server['host'] ?? '';
            $serverPort = $bundleSpan->context->server['port'] ?? 0;

            if ($serverHost !== '') {
                $context->setLabel('server_host', $serverHost);
            }
            if ($serverPort > 0) {
                $context->setLabel('server_port', $serverPort);
            }
        }
    }

    protected function fillHttpClientSpan(BundleSpan $bundleSpan, SpanInterface $span): void
    {
        $http = $span->context()->http();

        if ($bundleSpan->context->http_request !== null) {
            $requestMethod = $bundleSpan->context->http_request['method'] ?? '';

            if ($requestMethod !== '') {
                $http->setMethod($requestMethod);
            }

            $requestUrl = $bundleSpan->context->http_request['url'] ?? null;

            if ($requestUrl !== null) {
                $requestUrlDomain = $requestUrl['domain'] ?? '';
                $requestUrlPath = $requestUrl['path'] ?? '';
                $requestUrlPort = $requestUrl['port'] ?? 0;
                $requestUrlScheme = $requestUrl['scheme'] ?? '';

                if ($requestUrlDomain !== '') {
                    $targetName = $requestUrlDomain . ($requestUrlPort > 0 ? ':' . $requestUrlPort : '');
                } else {
                    $targetName = '';
                }

                if ($targetName !== '') {
                    if ($requestUrlScheme !== '') {
                        $http->setUrl($requestUrlScheme . '://' . $targetName . $requestUrlPath);
                    }

                    $this->fillSpanTarget($span, 'http', $targetName, true);

                    $span->setName("$requestMethod $targetName");
                }
            }
        }

        if ($bundleSpan->context->http_response !== null) {
            $responseStatusCode = $bundleSpan->context->http_response['status_code'] ?? 0;

            if ($responseStatusCode > 0) {
                $http->setStatusCode($responseStatusCode);
            }
        }
    }

    protected function fillMessagingSpan(BundleSpan $bundleSpan, SpanInterface $span): void
    {
        $framework = $bundleSpan->getSubtype() ?? '';
        $queue = $bundleSpan->context->message['queue_name'] ?? '';

        $span->setName($this->makeMessagingSegmentName('SEND', 'to', $framework, $queue));

        $context = $span->context();

        if ($bundleSpan->context->message !== null) {
            $messageQueueName = $bundleSpan->context->message['queue_name'] ?? '';
            $messageConsumerName = $bundleSpan->context->message['consumer_name'] ?? '';
            $messageName = $bundleSpan->context->message['name'] ?? '';
            $messageRetryAttempt = $bundleSpan->context->message['retry_attempt'] ?? 0;
            $messageRetryDelay = $bundleSpan->context->message['retry_delay'] ?? 0;

            if ($messageConsumerName !== '') {
                $context->setLabel('message_consumer_name', $messageConsumerName);
            }
            if ($messageName !== '') {
                $context->setLabel('message_name', $messageName);
            }
            if ($messageRetryAttempt > 0) {
                $context->setLabel('message_retry_attempt', $messageRetryAttempt);
            }
            if ($messageRetryDelay > 0) {
                $context->setLabel('message_retry_delay', $messageRetryDelay);
            }

            $targetType = $bundleSpan->getSubtype();
            $targetName = $messageQueueName !== '' ? $messageQueueName : null;

            $this->fillSpanTarget($span, $targetType, $targetName);
        }

        if ($bundleSpan->context->server !== null) {
            $serverHost = $bundleSpan->context->server['host'] ?? '';
            $serverPort = $bundleSpan->context->server['port'] ?? 0;

            if ($serverHost !== '') {
                $context->setLabel('server_host', $serverHost);
            }
            if ($serverPort > 0) {
                $context->setLabel('server_port', $serverPort);
            }
        }
    }

    protected function fillProfilingSpan(BundleSpan $bundleSpan, SpanInterface $span): void
    {
        $span->setName('Profile');

        $stacktrace = $bundleSpan->context->profile['stacktrace'] ?? [];

        if ($stacktrace !== []) {
            $this->fillSpanStacktrace($span, $stacktrace);

            $name = $stacktrace[0] ?? '';

            if ($name !== '') {
                $span->setName($name);
            }
        }
    }

    /**
     * Заполняет service.target и destination.service.resource.
     *
     * destination.service.resource (требуется для совместимости):
     * - external type: resource = targetName
     * - иначе: resource = targetType/targetName или targetType
     */
    protected function fillSpanTarget(SpanInterface $span, ?string $targetType, ?string $targetName, bool $external = false): void
    {
        if ($targetType === null && $targetName === null) {
            return;
        }

        $context = $span->context();

        if (\method_exists($context, 'service')) {
            $context->service()->target()->setType($targetType);
            $context->service()->target()->setName($targetName);
        }

        if ($targetName === null) {
            $destination = $targetType;
        } elseif ($targetType === null || $external) {
            $destination = $targetName;
        } else {
            $destination = "{$targetType}/{$targetName}";
        }

        $span->context()->destination()->setService('', $destination, '');
    }

    protected function fillSpanStacktrace(SpanInterface $span, array $stacktrace): void
    {
        if ($stacktrace === []) {
            return;
        }

        if (!\class_exists(Span::class) || !\class_exists(StackTraceFrame::class)) {
            return;
        }

        if (!$span instanceof Span || !\property_exists($span, 'stackTrace')) {
            return;
        }

        $elasticStackTrace = [];

        foreach ($stacktrace as $call) {
            if (!\is_string($call) || $call === '') {
                continue;
            }

            $elasticStackTrace[] = new StackTraceFrame('', 0, $call);
        }

        if ($elasticStackTrace === []) {
            return;
        }

        $hack = function (array $stacktrace): void {
            /** @phpstan-ignore-next-line */
            $this->stackTrace = $stacktrace;
        };

        $hack->call($span, $elasticStackTrace);
    }

    protected function makeMessagingSegmentName(string $operation, string $postfix, string $framework, string $queue): string
    {
        switch (\strtolower($framework)) {
            case SpanBundle::SPAN_SUBTYPE_RABBITMQ:
                $framework = 'RabbitMQ';
                break;
            case SpanBundle::SPAN_SUBTYPE_REDIS:
                $framework = 'Redis';
                break;
            case SpanBundle::SPAN_SUBTYPE_MESSENGER:
                $framework = 'Symfony Messenger';
                break;
            case SpanBundle::SPAN_SUBTYPE_MSSQL:
            case SpanBundle::SPAN_SUBTYPE_MYSQL:
            case SpanBundle::SPAN_SUBTYPE_ORACLE:
            case SpanBundle::SPAN_SUBTYPE_POSTGRESQL:
            case SpanBundle::SPAN_SUBTYPE_SQLITE:
            case SpanBundle::SPAN_SUBTYPE_DOCTRINE:
                $framework = 'Doctrine';
                break;
            default:
                $framework = 'Messaging';
        }

        if ($queue === SpanBundle::UNKNOWN) {
            $queue = '';
        }

        if ($queue !== '') {
            return "{$framework} {$operation} {$postfix} {$queue}";
        }

        return "{$framework} {$operation}";
    }

    protected function endSegment(BundleSpan $span, ExecutionSegmentInterface $elasticSegment, float $defaultStartTime, float $defaultEndTime): void
    {
        $start = $this->secondsToMillis($span->getStartTime() ?? $defaultStartTime);
        $end = $this->secondsToMillis($span->getEndTime() ?? $defaultEndTime);

        if ($end < $start) {
            $end = $start;
        }

        $elasticSegment->end($end - $start);
    }

    protected function secondsToMicros(float $seconds): float
    {
        return \round($seconds * 1000 * 1000, 0, PHP_ROUND_HALF_UP);
    }

    protected function secondsToMillis(float $seconds): float
    {
        return \round($seconds * 1000, 3, PHP_ROUND_HALF_UP);
    }

    protected function makeSqlSpanName(string $databaseName, string $sql): string
    {
        $sql = \trim($sql);

        if (\preg_match('/^\s*(\w+)/', $sql, $matches) === 1) {
            $operation = \strtoupper($matches[1]);

            $tableName = $this->extractSqlTableName($sql, $operation);

            if ($tableName !== null) {
                return "{$operation} {$tableName}";
            }
        }

        return "QUERY {$databaseName}";
    }

    protected function extractSqlTableName(string $sql, string $operation): ?string
    {
        $matches = [];

        switch ($operation) {
            case 'SELECT':
                $result = \preg_match('/FROM\s+([^\s,;()]+)/i', $sql, $matches);
                break;
            case 'INSERT':
                $result = \preg_match('/^\s*INSERT\s+INTO\s+([^\s,;()]+)/i', $sql, $matches);
                break;
            case 'UPDATE':
                $result = \preg_match('/^\s*UPDATE\s+([^\s,;()]+)/i', $sql, $matches);
                break;
            case 'DELETE':
                $result = \preg_match('/^\s*DELETE\s+FROM\s+([^\s,;()]+)/i', $sql, $matches);
                break;
            default:
                $result = false;
        }

        if ($result === 1 && $matches !== []) {
            return $this->cleanSqlTableName($matches[1]);
        }

        return null;
    }

    protected function cleanSqlTableName(string $tableName): string
    {
        $tableName = \trim($tableName, '"`\'');

        if (\strpos($tableName, '.') !== false) {
            $parts = \explode('.', $tableName);

            return $parts[\array_key_last($parts)];
        }

        return $tableName;
    }
}
