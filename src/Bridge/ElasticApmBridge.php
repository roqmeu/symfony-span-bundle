<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Bridge;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\ExecutionSegmentInterface as ElasticExecutionSegmentInterface;
use Elastic\Apm\Impl\Span as ElasticSpanImpl;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\SpanInterface as ElasticSpanInterface;
use Elastic\Apm\TransactionInterface as ElasticTransactionInterface;
use Roqmeu\SpanBundle\SpanBundle;
use Roqmeu\SpanBundle\State\Span;
use Roqmeu\SpanBundle\State\Trace;
use Roqmeu\SpanBundle\Transport\Event\TraceEndedEvent;

/**
 * Экспорт SpanBundle trace в Elastic APM.
 *
 * Единицы измерения времени в Elastic APM PHP Agent:
 * - $timestamp (beginTransaction/beginChildSpan): микросекунды от Unix epoch (@see ElasticApmBridge::secondsToMicros)
 * - $duration (end()): миллисекунды с точностью до 3 знаков после запятой(@see ElasticApmBridge::secondsToMillis)
 *
 * Noop/sampling: если isNoop() или !isSampled(), контекст и дочерние спаны не экспортируются.
 *
 * @see https://github.com/elastic/apm/tree/main/specs/agents — спецификации Elastic APM agent
 */
class ElasticApmBridge
{
    /**
     * Экспорт завершённого trace в Elastic APM.
     *
     * Маппинг SpanBundle -> Elastic APM:
     * - Root span -> Transaction
     * - Child spans -> Span
     */
    public function onTraceEnded(TraceEndedEvent $event): void
    {
        if (!class_exists('Elastic\Apm\ElasticApm')) {
            return;
        }

        // Отменяем автоинструментированную транзакцию Elastic APM
        ElasticApm::getCurrentTransaction()->discard();

        $trace = $event->trace;

        $span = $trace->getSpan();

        if ($span === null || $span->getStartTime() === null) {
            return;
        }

        $defaultStartTime = $span->getStartTime();
        $defaultEndTime = $span->getEndTime() ?? \microtime(true);

        if ($defaultEndTime < $defaultStartTime) {
            return;
        }

        $segment = $this->createElasticTransaction($trace, $span, $defaultStartTime);

        if ($segment->isNoop() || !$segment->isSampled()) {
            $this->endElasticSegment($span, $segment, $defaultStartTime, $defaultEndTime);

            return;
        }

        $spanStack = [$span];
        $segmentStack = [$segment];

        foreach ($span->iterateChildrenEuler() as $childSpan) {
            $spanStackEnd = \end($spanStack);
            $segmentStackEnd = \end($segmentStack);

            if ($spanStackEnd === false || $segmentStackEnd === false) {
                continue;
            }

            if ($spanStackEnd !== $childSpan) {
                $spanStack[] = $childSpan;
                $segmentStack[] = $this->createElasticSpan($childSpan, $segmentStackEnd, $defaultStartTime);
            } else {
                \array_pop($spanStack);
                \array_pop($segmentStack);

                $this->endElasticSegment($childSpan, $segmentStackEnd, $defaultStartTime, $defaultEndTime);
            }
        }

        $this->endElasticSegment($span, $segment, $defaultStartTime, $defaultEndTime);
    }

    /**
     * Создаёт Elastic APM Transaction из корневого спана.
     *
     * Transaction types:
     * - server+http -> request, name = "<METHOD> <route>" или "<METHOD> unknown route"
     * - consumer    -> messaging, name = "<Framework> RECEIVE from <queue>"
     * - console     -> cli, name = command name
     * - прочее      -> custom
     */
    private function createElasticTransaction(Trace $trace, Span $span, float $defaultStartTime): ElasticTransactionInterface
    {
        $name = $span->getName();
        $type = 'custom';
        $result = null;

        if ($span->getType() === SpanBundle::SPAN_TYPE_SERVER && $span->getSubtype() === SpanBundle::SPAN_SUBTYPE_HTTP) {
            $method = \strtoupper($span->context->http_request['method'] ?? '');

            $route = $span->context->http_request['route'] ?? '';

            if ($route !== '') {
                $name = \trim(($method !== '' ? $method . ' ' : '') . $route);
            } elseif ($method !== '') {
                $name = $method . ' unknown route';
            } else {
                $name = 'unknown route';
            }

            $statusCode = $span->context->http_response['status_code'] ?? null;

            if (\is_int($statusCode) && $statusCode > 0) {
                $statusCode = \intdiv($statusCode, 100);

                if ($statusCode > 0) {
                    $result = "HTTP {$statusCode}xx";
                }
            }

            $type = 'request';
        } elseif ($span->getType() === SpanBundle::SPAN_TYPE_CONSUMER) {
            $framework = $span->getSubtype() ?? $span->context->target['type'] ?? '';
            $queue = $span->context->message['queue_name'] ?? $span->context->target['name'] ?? '';

            $name = $this->makeSegmentMessagingName('RECEIVE', 'from', $framework, $queue);

            $type = 'messaging';
        } elseif ($span->getType() === SpanBundle::SPAN_TYPE_CONSOLE) {
            $commandName = $span->context->command['name'] ?? '';

            if ($commandName !== '') {
                $name = $commandName;
            }

            $type = 'cli';
        }

        if ($name === '') {
            $name = 'unnamed';
        }

        $elasticTransactionBuilder = ElasticApm::newTransaction($name, $type)->timestamp($this->secondsToMicros($span->getStartTime() ?? $defaultStartTime));

        $traceId = $trace->getId();
        $traceParentId = $trace->getParent();

        if ($traceParentId !== null) {
            $elasticTransactionBuilder->distributedTracingHeaderExtractor(
                static function (string $headerName) use ($traceId, $traceParentId): ?string {
                    if ($headerName === 'traceparent') {
                        return "00-{$traceId}-{$traceParentId}-01";
                    }

                    return null;
                }
            );
        }

        $elasticTransaction = $elasticTransactionBuilder->begin();

        if ($elasticTransaction->isNoop() || !$elasticTransaction->isSampled()) {
            return $elasticTransaction;
        }

        $this->fillTransactionContext($span, $elasticTransaction);

        $elasticTransaction->setResult($result);

        $elasticTransaction->setOutcome($span->isSuccessful() ? 'success' : 'failure');

        $error = $span->getError();

        if ($error !== null) {
            $elasticTransaction->createErrorFromThrowable($error);
        }

        return $elasticTransaction;
    }

    /**
     * Заполняет контекст транзакции (labels, request).
     *
     * Labels (php_*): framework info, process info, command name.
     * Request context: method, url (для HTTP server транзакций).
     */
    private function fillTransactionContext(Span $span, ElasticTransactionInterface $elasticTransaction): void
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

    private function createElasticSpan(Span $span, ElasticExecutionSegmentInterface $elasticParent, float $defaultStartTime): ElasticSpanInterface
    {
        $name = $span->getName();

        if ($name === '') {
            $name = 'unnamed';
        }

        $elasticSpan = $elasticParent->beginChildSpan(
            $name,
            $this->mapToElasticSpanType($span),
            $this->mapToElasticSpanSubtype($span),
            $this->mapToElasticSpanAction($span),
            $this->secondsToMicros($span->getStartTime() ?? $defaultStartTime)
        );

        if (\method_exists($elasticSpan, 'setCompressible')) {
            $elasticSpan->setCompressible(true);
        }

        $this->fillElasticSpanContext($span, $elasticSpan);

        $elasticSpan->setOutcome($span->isSuccessful() ? 'success' : 'failure');

        $error = $span->getError();

        if ($error !== null) {
            $elasticSpan->createErrorFromThrowable($error);
        }

        return $elasticSpan;
    }

    private function fillElasticSpanContext(Span $span, ElasticSpanInterface $elasticSpan): void
    {
        $type = $span->getType();
        $subtype = $span->getSubtype();

        if ($type === SpanBundle::SPAN_TYPE_DB) {
            $this->fillDbSpanContext($span, $elasticSpan);
        } elseif ($type === SpanBundle::SPAN_TYPE_CLIENT && $subtype === SpanBundle::SPAN_SUBTYPE_HTTP) {
            $this->fillHttpClientSpanContext($span, $elasticSpan);
        } elseif ($type === SpanBundle::SPAN_TYPE_PRODUCER || $type === SpanBundle::SPAN_TYPE_CONSUMER) {
            $this->fillMessagingSpanContext($span, $elasticSpan);
        } elseif ($type === SpanBundle::SPAN_TYPE_INTERNAL && $subtype === SpanBundle::SPAN_SUBTYPE_PROFILE) {
            $this->fillElasticSpanStackTrace($elasticSpan, $span->context->profile['stacktrace'] ?? []);
        }
    }

    /**
     * DB span: context.db.statement, service.target (type=subtype, name=instance).
     */
    private function fillDbSpanContext(Span $span, ElasticSpanInterface $elasticSpan): void
    {
        $context = $elasticSpan->context();

        if ($span->context->db !== null) {
            $dbStatement = $span->context->db['statement'] ?? '';

            if ($dbStatement !== '') {
                $elasticSpan->context()->db()->setStatement($dbStatement);
            }

            $dbInstance = $span->context->db['instance'] ?? '';

            $targetType = $span->getSubtype();
            $targetName = $dbInstance !== '' ? $dbInstance : null;

            $this->fillSpanTargetContext($elasticSpan, $targetType, $targetName);
        }

        if ($span->context->server !== null) {
            $serverHost = $span->context->server['host'] ?? '';
            $serverPort = $span->context->server['port'] ?? 0;

            if ($serverHost !== '') {
                $context->setLabel('server_host', $serverHost);
            }
            if ($serverPort > 0) {
                $context->setLabel('server_port', $serverPort);
            }
        }
    }

    /**
     * HTTP client span: context.http (url, method, status_code), service.target (type=http, name=host:port).
     */
    private function fillHttpClientSpanContext(Span $span, ElasticSpanInterface $elasticSpan): void
    {
        $http = $elasticSpan->context()->http();

        if ($span->context->http_request !== null) {
            $requestMethod = $span->context->http_request['method'] ?? '';

            if ($requestMethod !== '') {
                $http->setMethod($requestMethod);
            }

            $requestUrl = $span->context->http_request['url'] ?? null;

            if ($requestUrl !== null) {
                $requestUrlDomain = $requestUrl['domain'] ?? '';
                $requestUrlPath = $requestUrl['path'] ?? '';
                $requestUrlPort = $requestUrl['port'] ?? 0;
                $requestUrlScheme = $requestUrl['scheme'] ?? '';

                if ($requestUrlScheme !== '' && $requestUrlDomain !== '') {
                    $fullUrl = $requestUrlScheme . '://' . $requestUrlDomain . ($requestUrlPort > 0 ? ':' . $requestUrlPort : '') . $requestUrlPath;

                    $http->setUrl($fullUrl);
                }
            }
        }

        if ($span->context->http_response !== null) {
            $responseStatusCode = $span->context->http_response['status_code'] ?? 0;

            if ($responseStatusCode > 0) {
                $http->setStatusCode($responseStatusCode);
            }
        }

        if ($span->context->target !== null) {
            $targetType = 'http';
            $targetName = $span->context->target['name'] ?? null;

            $this->fillSpanTargetContext($elasticSpan, $targetType, $targetName, true);
        }
    }

    /**
     * Messaging span: labels (consumer_name, name, retry_attempt, retry_delay), service.target (type=subtype, name=queue).
     */
    private function fillMessagingSpanContext(Span $span, ElasticSpanInterface $elasticSpan): void
    {
        $framework = $span->getSubtype() ?? $span->context->target['type'] ?? '';
        $queue = $span->context->message['queue_name'] ?? $span->context->target['name'] ?? '';

        $elasticSpan->setName($this->makeSegmentMessagingName('SEND', 'to', $framework, $queue));

        $context = $elasticSpan->context();

        if ($span->context->message !== null) {
            $messageQueueName = $span->context->message['queue_name'] ?? '';
            $messageConsumerName = $span->context->message['consumer_name'] ?? '';
            $messageName = $span->context->message['name'] ?? '';
            $messageRetryAttempt = $span->context->message['retry_attempt'] ?? 0;
            $messageRetryDelay = $span->context->message['retry_delay'] ?? 0;

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

            $targetType = $span->getSubtype();
            $targetName = $messageQueueName !== '' ? $messageQueueName : null;

            $this->fillSpanTargetContext($elasticSpan, $targetType, $targetName);
        }

        if ($span->context->server !== null) {
            $serverHost = $span->context->server['host'] ?? '';
            $serverPort = $span->context->server['port'] ?? 0;

            if ($serverHost !== '') {
                $context->setLabel('server_host', $serverHost);
            }
            if ($serverPort > 0) {
                $context->setLabel('server_port', $serverPort);
            }
        }
    }

    /**
     * Заполняет service.target и destination.service.resource.
     *
     * destination.service.resource (deprecated, но требуется для совместимости):
     * - external type: resource = targetName (host:port для HTTP)
     * - иначе: resource = targetType/targetName или targetType
     */
    private function fillSpanTargetContext(ElasticSpanInterface $elasticSpan, ?string $targetType, ?string $targetName, bool $external = false): void
    {
        if ($targetType === null && $targetName === null) {
            return;
        }

        $context = $elasticSpan->context();

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

        $elasticSpan->context()->destination()->setService('', $destination, '');
    }

    private function mapToElasticSpanType(Span $span): string
    {
        switch ($span->getType()) {
            case SpanBundle::SPAN_TYPE_DB:
                return 'db';
            case SpanBundle::SPAN_TYPE_CLIENT:
                return 'external';
            case SpanBundle::SPAN_TYPE_PRODUCER:
            case SpanBundle::SPAN_TYPE_CONSUMER:
                return 'messaging';
            case SpanBundle::SPAN_TYPE_INTERNAL:
                return 'app';
            default:
                return $span->getType() !== '' ? $span->getType() : 'custom';
        }
    }

    private function mapToElasticSpanSubtype(Span $span): ?string
    {
        $subtype = $span->getSubtype();

        return $subtype !== '' ? $subtype : null;
    }

    private function mapToElasticSpanAction(Span $span): ?string
    {
        switch ($span->getType()) {
            case SpanBundle::SPAN_TYPE_PRODUCER:
                return 'send';
            case SpanBundle::SPAN_TYPE_CONSUMER:
                return 'receive';
            default:
                return null;
        }
    }

    private function fillElasticSpanStackTrace(ElasticSpanInterface $elasticSpan, array $stacktrace): void
    {
        if ($stacktrace === []) {
            return;
        }

        if (!\class_exists(ElasticSpanImpl::class) || !\class_exists(StackTraceFrame::class)) {
            return;
        }

        if (!$elasticSpan instanceof ElasticSpanImpl || !\property_exists($elasticSpan, 'stackTrace')) {
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

        $hack->call($elasticSpan, $elasticStackTrace);
    }

    private function makeSegmentMessagingName(string $operation, string $postfix, string $framework, string $queue): string
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

    /**
     * Завершает Elastic APM segment с вычисленной duration (миллисекунды).
     */
    private function endElasticSegment(Span $span, ElasticExecutionSegmentInterface $elasticSegment, float $defaultStartTime, float $defaultEndTime): void
    {
        $start = $this->secondsToMillis($span->getStartTime() ?? $defaultStartTime);
        $end = $this->secondsToMillis($span->getEndTime() ?? $defaultEndTime);

        if ($end < $start) {
            $end = $start;
        }

        $elasticSegment->end($end - $start);
    }

    private function secondsToMicros(float $seconds): float
    {
        return \round($seconds * 1000 * 1000, 0, PHP_ROUND_HALF_UP);
    }

    private function secondsToMillis(float $seconds): float
    {
        return \round($seconds * 1000, 3, PHP_ROUND_HALF_UP);
    }
}
