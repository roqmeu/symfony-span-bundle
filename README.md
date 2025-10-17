# Symfony Span bundle

Symfony bundle that adds tracing and instrumentation for HTTP, Doctrine, Messenger and other components — ready for integration with Elastic APM, OpenTelemetry and similar observability systems.

## Основные концепции

Этот бандл использует свои DTO разработанные на основе стандарта OpenTelemetry, но сильно облегчённые для передачи между слоями.

Бандл ориентируется на наименование и типизирование спанов в OpenTelemetry, ElasticCommonSchema и Sentry.

Поддерживается автоматическая обработка Symfony HTTP Client, Guzzle HTTP Client, Doctrine DBAL, Symfony Messenger (Sync, AMQP, Redis, Doctrine).

Основной формат коммуникации - SpanBundle отправляет Event в EventDispatcher, транспорт слушает эвенты и отправляет по своей реализации. Зона ответственности SpanBundle завершается после отправки эвента в EventDispatcher.

### TracePool

Может быть ситуация "наслаивания" Trace, например во время работы roadrunner контроллера или consumer:

- Trace 1 создаётся - запуск cli команды.
- Все новые спаны прикрепляются к Trace 1.
- Trace 2 создаётся - получение сообщения из messenger.
- Все новые спаны прикрепляются к Trace 2.
- Сообщение обработано - Trace 2 завершается и диспатчится.
- Все новые спаны прикрепляются к Trace 1.
- Trace 3 создаётся - получение сообщения из messenger.
- Все новые спаны прикрепляются к Trace 3.
- Сообщение обработано - Trace 3 завершается и диспатчится.
- Все новые спаны прикрепляются к Trace 1.
- Команда завершена - Trace 1 завершается и диспатчится.

Исходя из этого мы получаем, что Trace всегда создаётся для Main запросов Controller и Consumer, а для не Main запросов и Command Trace создаётся только если нет корневого Trace.

Для возможности проверки существования Trace нам нужно единое хранилище - TracePool.

TracePool хранит стек в памяти. Для предотвращения утечек он реализует ResettableInterface.

## Стандартные схемы типизации спанов и транзакций

### OpenTelemetry

OpenTelemetry сочетает роль спана (SpanKind) и семантические атрибуты.

- SpanKind: INTERNAL, CLIENT, SERVER, PRODUCER, CONSUMER — определяет роль спана.
- Semantic attributes: детали по доменам (HTTP/DB/Messaging/Exception/Network) в атрибуты; имя держим низкой кардинальности.
- Status: OK | ERROR | UNSET.
- Именование:
  - server: `METHOD <route>`
  - client: `METHOD <host>` или `METHOD <route>`
  - db: `<OPERATION> <table|collection>`
  - messaging: `<operation> <destination>`

### ElasticAPM (Elastic Common Schema)

ElasticAPM использует иерархию `type.subtype.action`.

- Transactions: `request`, `messaging`, `background`, `cli`.
- Spans: `db`, `external`, `cache`, `template`, `messaging`, `app` с соответствующим `subtype` и `action`.
- Outcome: `success`, `failure`, `unknown`.
- Именование и типизация:
  - HTTP client: `type=external`, `subtype=http`, `action=GET|POST|...`, name: `GET <host>`.
  - HTTP server: transaction `type=request`, name: `METHOD <route>`.
  - DB: `type=db`, `subtype=<system>`, `action=query|execute|...`, name: `SELECT <table>`.
  - Messaging: `type=messaging`, `subtype=<system>`, `action=send|receive|process`.

### Sentry

Sentry типизирует через поле `op`.

- `op`: строка `category.subcategory` (например, `http.server`, `http.client`, `db.sql.query`, `queue.process`, `console.command`, `middleware.handle`).
- Status: ориентируемся на HTTP/gRPC status_code — `<400` - `ok`, `4xx` - клиентские ошибки, `5xx` - `internal_error`.
- Именование:
  - Транзакции HTTP server: `name` по route, иначе `url`.
  - Детали высокой кардинальности — в `description`, `data`, `tags` не в `name` или `op`.

## Схема типизации SpanBundle

Используется упрощенная схема `type` + `subtype`: проста для разработчиков, хорошо агрегируется и гибко маппится в целевые системы.

Маппинг в целевые системы выполняется транспортами на стороне интеграций.

### Использование наименований

- Trace - без имени.
- Span: `type` + `subtype` + низкокардинальное имя.
- Рекомендации имён:
  - HTTP server: `METHOD <route>`
  - HTTP client: `METHOD <host>`
  - DB: `<OPERATION> <table>`
  - Messaging: `<OPERATION> <destination>`
  - Console: `<command>`

### Использование типов

- HTTP: `server|client` + `http`
- DB: `db` + `<system>` (например, `postgresql`)
- Messaging: `producer|consumer` + `<system>` (например, `rabbitmq`, `kafka`)
- Console: `console`
- Internal/App/Profile: `internal` + `app|profile`

### Дополнительный контекст

Высококардинальные и протокольные детали помещаются в `SpanContext` для последующего маппинга в целевые системы.

## Поддерживаемые интеграции

### Doctrine DBAL

Автоматическое отслеживание SQL запросов через Doctrine DBAL Middleware.

**Что трассируется:**
- SELECT, INSERT, UPDATE, DELETE, TRUNCATE, CREATE, DROP, ALTER запросы
- Prepared statements (prepare + execute)
- Транзакции (BEGIN, COMMIT, ROLLBACK)

**Собираемые метаданные:**
- Тип БД (PostgreSQL, MySQL, SQLite и т.д.)
- Имя базы данных
- Адрес и порт сервера БД
- SQL запрос (без параметров для безопасности)
- Имя таблицы (извлекается из SQL)

**Именование спанов:**
- Формат: `<OPERATION> <table>` (например, `SELECT users`, `UPDATE orders`)
- Для сложных запросов: `<OPERATION> <database_name>`
- Транзакции: `BEGIN TRANSACTION`, `COMMIT`, `ROLLBACK`

**Типизация:**
- type: `db`
- subtype: `postgresql` | `mysql` | `sqlite` | `doctrine`

## На будущее

- Добавить контекст в Messenger
- Добавить RabbitMqBundle интеграцию
- Переделать RootSpan в Transaction
- Перенести Dto в src и переименовать
- Перенести Pool в src или State
- Добавить прокидывание TraceId через GuzzleHttpClient, SymfonyHttpClient, SymfonyMessenger, RabbitMqBundle
- Добавить в TracingProducerMiddleware поддержку отправки в несколько каналов
