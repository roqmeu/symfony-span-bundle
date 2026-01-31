<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use OldSound\RabbitMqBundle\RabbitMq\MultipleConsumer;

class TracingMultipleConsumer extends MultipleConsumer
{
    use RabbitMqConsumerTracingTrait;
}
