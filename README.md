# Symfony Span bundle

Symfony-бандл, добавляющий трассировку и инструментирование для HTTP, Doctrine, Messenger и других компонентов. Готов к интеграции с Elastic APM, OpenTelemetry и подобными системами наблюдаемости.

## Возможности

- Автоинструментирование Symfony HTTP Client, Guzzle, Doctrine DBAL, Symfony Messenger и OldSound RabbitMqBundle.
- Централизованное управление трейсами и спанами, совместимое с OpenTelemetry naming-конвенциями.
- Событийная модель работы: бандл диспатчит события жизненного цикла спанов/трейсов; адаптеры экспортируют данные во внешние системы.
- Поддержка Distributed Tracing для передачи контекста трассировки через HTTP и очереди сообщений.
- Встроенный мост к Elastic APM, учитывающий спецификации агента, маппинг контекста и поддерживающий Distributed Tracing.
- Профайлинг приложения через `ext-excimer`.

## Требования

- PHP `^7.4` или `^8.0`.
- Symfony компоненты (Config, DependencyInjection, EventDispatcher) версий `^5.0 || ^6.0 || ^7.0`.
- Поддержка Symfony Messenger `^5.4 || ^6.4 || ^7.0`.
- Поддержка Doctrine DBAL `^3.0 || ^4.0`.
- Поддержка Guzzle `^7.0`.
- Поддержка OldSound RabbitMqBundle `^2.0`.
- Для профилинга (опционально): PHP-расширение `excimer` (`ext-excimer`).

## Установка

```bash
composer require roqmeu/symfony-span-bundle
```

Если вы не используете Symfony Flex, добавьте бандл вручную в `config/bundles.php`:

```php
return [
    // ...
    Roqmeu\SpanBundle\SpanBundle::class => ['all' => true],
];
```

После установки бандл предоставляет сервисы `SpanTracer`, `SpanInteractor` и `EventDispatcher`, которые можно внедрять через автоконфигурацию.

## Быстрый старт

1. Включите бандл и трассировку в конфиге (по умолчанию всё выключено).
2. Включите мост, например, `span.bridge.elastic_apm` (по умолчанию всё выключено).
3. Подключите бандл и соберите контейнер.
4. Подпишитесь на события `TraceStartedEvent`, `TraceEndedEvent`, `SpanStartedEvent`, `SpanEndedEvent`, если требуется собственный экспорт.
5. Используйте `SpanTracer` для создания и завершения спанов в пользовательском коде или полагайтесь на готовые интеграции.

## Конфигурация

По умолчанию бандл **выключен**. Автоинструментирование и листенеры включаются только при явном включении флагов.

Пример `config/packages/span.yaml`:

```yaml
span:
  enabled: true

  tracing:
    enabled: true

  bridge:
    elastic_apm:
      # bool или placeholder (%env(bool:...)% или bool %parameter%)
      enabled: '%env(bool:default::ELASTIC_APM_ENABLED)%'
      # bool или placeholder (%env(bool:...)% или bool %parameter%)
      use_span_compression: false

  profiling:
    # bool или placeholder (%env(bool:...)% или bool %parameter%)
    enabled: '%env(bool:default::PROFILER_ENABLED)%'

    # Порог (секунды). Минимум 0.01, по умолчанию 0.1.
    threshold: 0.1

    # Фильтры корневого спана, для которого включаем профилинг.
    # Если задан allowed_*, то соответствующий ignored_* игнорируется.
    allowed_types: ~
    ignored_types: [ console ]
    allowed_subtypes: ~
    ignored_subtypes: ~
```

Ключевые моменты:

- `span.enabled`: включает регистрацию сервисов `SpanTracer`/`SpanInteractor`/`EventDispatcher`. Если `false`, регистрируются null-реализации (`NullSpanTracer`, `NullSpanInteractor`, `NullEventDispatcher`).
- `span.enabled` и `span.tracing.enabled` влияют на сборку контейнера (регистрация сервисов и compiler passes), поэтому их следует задавать как обычные `true/false` (не placeholder).
- `span.tracing.enabled`: включает листенеры HTTP/Console и автоинструментирование (Doctrine DBAL middleware, Symfony HttpClient, Guzzle, Symfony Messenger, OldSound RabbitMqBundle). Работает только если `span.enabled: true`.
- `span.profiling.enabled`: включает профилинг через `ext-excimer` (если расширение отсутствует — используется `SpanNullProfiler`). Работает только если `span.enabled: true`.
- `span.bridge.elastic_apm.enabled`: включает регистрацию `ElasticApmBridge` и его обработчики событий. Можно задавать как placeholder — в этом случае сервис будет зарегистрирован, а фактическое включение/выключение будет происходить по значению параметра/ENV в рантайме.
- `span.bridge.elastic_apm.use_span_compression`: настройка для Distributed Tracing при использовании span compression в Elastic APM агенте. Можно задавать как placeholder.

## Архитектура и концепции

### Типизация и именование

Бандл опирается на стандарты OpenTelemetry, Elastic Common Schema и Sentry.

- Span содержит пару `type` + `subtype` и `сontext`.
- Ответственность за генерацию id, имени передана мостам (например, ElasticApmBridge), которые вычисляют их по собственным правилам на основе контекста:
  - HTTP server: `METHOD <route>`
  - HTTP client: `METHOD <host>`
  - DB: `<OPERATION> <table>`
  - Messaging: `<OPERATION> <destination>`
  - Console: `<command>`
- Контекст высокой кардинальности хранится в `Roqmeu\SpanBundle\State\Context`.

## Поддерживаемые интеграции

### HTTP (Symfony HttpClient & Guzzle)

- Автоинструментирование клиентов и ответов, с поддержкой потоковой обработки chunk'ов.
- Сбор HTTP-метаданных (метод, URI, статус, целевой хост/порт).

### Symfony HTTP Server

- `TracingRequestListener` создаёт root span для входящих HTTP-запросов.
- Определяет имя транзакции по маршруту и выставляет `successful` в зависимости от статуса ответа.

### Console команды

- `TracingCommandListener` создаёт и завершает трассы вокруг Symfony Console команд.
- Имя транзакции совпадает с именем команды.

### Doctrine DBAL

- Middleware перехватывает запросы, транзакции и prepared statements.
- Заполняет тип `db.<driver>` и контекст (instance, statement, адрес сервера).

### Symfony Messenger

- Автоинструментирование producer/consumer для sync и Redis/AMQP драйверов.
- Создаёт `producer` и `consumer` спаны, нормализует имена очередей, переносит контекст цели и заполняет `server.host`/`server.port` по параметрам соединения.

### OldSound RabbitMqBundle

- Автоинструментирование producer/consumer (включая batch, multiple, dynamic, anon).
- Создаёт `producer` и `consumer` спаны, нормализует имена очередей, переносит контекст цели и заполняет `server.host`/`server.port` по параметрам соединения.

## Экспорт и события

Все интеграции сообщают о жизненном цикле через события:

- `TraceStartedEvent` / `TraceEndedEvent`
- `SpanStartedEvent` / `SpanEndedEvent`

### Elastic APM Bridge

`Roqmeu\SpanBundle\Bridge\ElasticApmBridge` подписан на `SpanStartedEvent` и `TraceEndedEvent` и экспортирует Trace в Elastic APM, соблюдая требования агента:

- Корневой спан преобразуется в транзакцию с корректным `type`, `outcome` и `result`. Имя транзакции генерируется мостом на основе контекста.
- Дочерние спаны создаются через `beginChildSpan`, учитывая тип и сабтайп.
- Контекст HTTP/DB/Messaging маппится в `destination` и `service.target`. Мост самостоятельно выбирает `target` на основе контекста.
- Поддерживается Distributed Tracing за счёт "раннего" создания транзакций и спанов (stubs), так как Elastic APM Agent позволяет инжектировать заголовки `traceparent` только на этапе создания сегмента.

Мост по умолчанию выключен. Для включения задайте `span.bridge.elastic_apm.enabled: true` (или placeholder) и убедитесь, что установлен и доступен `elastic/apm-agent-php` (проверяется по наличию класса `Elastic\Apm\ElasticApm`).
Если вы хотите получать спаны из автоинструментирования Symfony/Doctrine/Messenger, дополнительно включайте `span.tracing.enabled: true`.

Если нужна другая система экспорта, подпишитесь на события и реализуйте собственный транспорт.

## Локальная разработка

В репозитории присутствует `docker-compose.yml` и `Makefile`, упрощающие запуск окружения:

- `.env` содержит версии PHP и библиотек.
- `make up` — поднимает окружение (PHP + PostgreSQL + Redis).
- `make init` - устанавливает указанные в `.env` версии библиотек.
- `make reup` — перезапускает контейнеры с пересозданием.
- `make rebi` — пересобирает образ PHP и обновляет контейнеры.
- `make sh` / `make shr` — открывает shell внутри контейнера от имени `www-data` или `root`.

## Тестирование и качество

- `make lint` — запускает Easy Coding Standard (`ecs`).
- `make stan` — запускает PHPStan с проектной конфигурацией.
- `make test` — обновляет автозагрузчик и запускает Codeception функциональные тесты.
- `make chain` — выполняет полный цикл: очистка кеша, линтер, статический анализ, тесты.

Конфигурация Codeception лежит в `codeception.yml`, тестовые фикстуры — в `test/fixture`, функциональные сценарии — в `test/functional`.

## План развития

- Добавить поддержку многоканальной отправки в `TracingProducerMiddleware`.
