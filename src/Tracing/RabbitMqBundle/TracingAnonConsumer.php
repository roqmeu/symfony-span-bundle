<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Tracing\RabbitMqBundle;

use OldSound\RabbitMqBundle\RabbitMq\AnonConsumer;

class TracingAnonConsumer extends AnonConsumer
{
    use RabbitMqConsumerTracingTrait;
}
