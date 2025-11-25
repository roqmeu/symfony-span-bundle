# Symfony Span bundle

Symfony-бандл, добавляющий трассировку и инструментирование для HTTP, Doctrine, Messenger и других компонентов. Готов к интеграции с Elastic APM, OpenTelemetry и подобными системами наблюдаемости.

## Возможности

- Автоинструментирование Symfony HTTP Client, Guzzle, Doctrine DBAL и Symfony Messenger (sync, AMQP, Redis, Doctrine transport).
- Централизованное управление трейсами и спанами, совместимое с OpenTelemetry naming-конвенциями.
- Событийная модель работы: бандл диспатчит события жизненного цикла спанов/трейсов; адаптеры экспортируют данные во внешние системы.
- Встроенный мост к Elastic APM, учитывающий спецификации агента и маппинг контекста.
- Профилинг корневых спанов через `ext-excimer` с преобразованием профиля в `internal.profile` спаны.

## Требования

- PHP `^7.4` или `^8.0`.
- Symfony компоненты (Config, DependencyInjection, EventDispatcher) версий `^5.0 || ^6.0 || ^7.0`.
- Поддержка Doctrine DBAL `^3.0`, Guzzle `^7.0`, Symfony Messenger `^5.4 || ^6.4 || ^7.0` для dev/test окружения.
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
2. Подключите бандл и соберите контейнер.
3. Подпишитесь на события `TraceStartedEvent`, `TraceEndedEvent`, `SpanStartedEvent`, `SpanEndedEvent`, если требуется собственный экспорт.
4. Используйте `SpanTracer` для создания и завершения спанов в пользовательском коде или полагайтесь на готовые интеграции.

## Конфигурация

По умолчанию бандл **выключен**. Автоинструментирование и листенеры включаются только при явном включении флагов.

Пример `config/packages/span.yaml`:

```yaml
span:
  enabled: true

  tracing:
    enabled: true

  profiling:
    # bool или placeholder (%env(...)% / %parameter%)
    enabled: '%env(bool:default::SPAN_PROFILER_ENABLED)%'

    # Порог (секунды). Минимум 0.01, по умолчанию 0.1.
    threshold: 0.1

    # Фильтры корневого спана, для которого включаем профилинг.
    # Если задан allowed_* — соответствующий ignored_* игнорируется.
    allowed_types: [server, consumer]
    ignored_types: ~
    allowed_subtypes: ~
    ignored_subtypes: ~
```

Ключевые моменты:

- `span.enabled`: включает регистрацию сервисов `SpanTracer`/`SpanInteractor`/`EventDispatcher`. Если `false`, регистрируются null-реализации (`NullSpanTracer`, `NullSpanInteractor`, `NullEventDispatcher`).
- `span.enabled` и `span.tracing.enabled` влияют на сборку контейнера (регистрация сервисов и compiler passes), поэтому их следует задавать как обычные `true/false` (не placeholder).
- `span.tracing.enabled`: включает листенеры HTTP/Console и автоинструментирование (Doctrine DBAL middleware, Symfony HttpClient, Guzzle, Symfony Messenger). Работает только если `span.enabled: true`.
- `span.profiling.enabled`: включает профилинг через `ext-excimer` (если расширение отсутствует — используется `SpanNullProfiler`). Работает только если `span.enabled: true`.

## Архитектура и концепции

### TracePool

Трейсы хранятся в памяти стеком. Это позволяет корректно обрабатывать вложенные сценарии (например, CLI-команда запускает consumer):

1. CLI-команда запускает Trace 1.
2. Сообщение Messenger создаёт Trace 2, который завершается и диспатчится по завершении обработки.
3. Стек возвращается к Trace 1, цикл повторяется для следующих сообщений.

`TracePool` реализует `ResettableInterface`, поэтому не держит ссылки между запросами в worker-сценариях.

### Типизация и именование

Бандл опирается на стандарты OpenTelemetry, Elastic Common Schema и Sentry.

- Trace создаётся без имени.
- Span получает пару `type` + `subtype` и низкокардинальное имя.
- Рекомендации по именованию:
  - HTTP server: `METHOD <route>`
  - HTTP client: `METHOD <host>`
  - DB: `<OPERATION> <table>`
  - Messaging: `<OPERATION> <destination>`
  - Console: `<command>`
- Контекст высокой кардинальности хранится в `Roqmeu\SpanBundle\State\Context` и передаётся транспортами.

## Поддерживаемые интеграции

### HTTP (Symfony HttpClient & Guzzle)

- Автообёртка клиентов и ответов.
- Типизация спанов как `client.http`.
- Сбор HTTP-метаданных (метод, URI, статус, целевой хост/порт) и заполнение `service.target`.
- Поддержка `HttpClientInterface::stream()` и потоковой обработки chunk'ов.

### Symfony HTTP Server

- `TracingRequestListener` создаёт root span для входящих HTTP-запросов.
- Определяет имя транзакции по маршруту и выставляет `outcome` в зависимости от статуса ответа.

### Console команды

- `TracingCommandListener` создаёт и завершает трассы вокруг Symfony Console команд.
- Имя транзакции совпадает с именем команды.

### Doctrine DBAL

- Middleware перехватывает запросы, транзакции и prepared statements.
- Заполняет тип `db.<driver>` и контекст (instance, statement, адрес сервера).

### Symfony Messenger

- Producer/consumer middleware для sync и Redis/AMQP драйверов.
- Создаёт `producer` и `consumer` спаны, нормализует имена очередей и переносит контекст цели.

## Экспорт и события

Все интеграции сообщают о жизненном цикле через события:

- `TraceStartedEvent` / `TraceEndedEvent`
- `SpanStartedEvent` / `SpanEndedEvent`

Стандартная реализация `Roqmeu\SpanBundle\Transport\EventDispatcher\SymfonyEventDispatcher` проксирует события в `Symfony\Contracts\EventDispatcher\EventDispatcherInterface`.

### Elastic APM Bridge

`Roqmeu\SpanBundle\Bridge\ElasticApmBridge` подписан на `TraceEndedEvent` и экспортирует весь Trace в Elastic APM, соблюдая требования агента:

- Корневой спан преобразуется в транзакцию с корректным `type`, `name`, `outcome` и `result`.
- Дочерние спаны создаются через `beginChildSpan`, учитывая low-cardinality имя, тип и сабтайп.
- Контекст HTTP/DB/Messaging маппится в `destination` и `service.target`.

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

- Добавить расширенный контекст в интеграцию Messenger.
- Реализовать интеграцию с `RabbitMqBundle`.
- Добавить прокидывание и получение `trace-id` в клиенты (Guzzle, Symfony HttpClient, Messenger и т.д.).
- Поддержать использование `trace-id` в транспортах.
- Добавить поддержку Doctrine DBAL `^4.0`.
- Добавить поддержку многоканальной отправки в `TracingProducerMiddleware`.
