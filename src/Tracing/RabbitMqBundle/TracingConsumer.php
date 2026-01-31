<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use OldSound\RabbitMqBundle\RabbitMq\Consumer;

class TracingConsumer extends Consumer
{
    use RabbitMqConsumerTracingTrait;
}
