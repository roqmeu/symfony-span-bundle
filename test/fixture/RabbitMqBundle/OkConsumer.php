<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\RabbitMqBundle;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class OkConsumer implements ConsumerInterface
{
    public function execute(AMQPMessage $msg): int
    {
        return ConsumerInterface::MSG_ACK;
    }
}
