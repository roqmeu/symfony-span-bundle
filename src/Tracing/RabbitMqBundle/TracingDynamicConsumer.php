<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use OldSound\RabbitMqBundle\RabbitMq\DynamicConsumer;

class TracingDynamicConsumer extends DynamicConsumer
{
    use RabbitMqConsumerTracingTrait;
}
