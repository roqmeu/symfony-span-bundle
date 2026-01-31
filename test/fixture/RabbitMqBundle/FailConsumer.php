<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\RabbitMqBundle;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class FailConsumer implements ConsumerInterface
{
    public function execute(AMQPMessage $msg): int
    {
        throw new \RuntimeException('RabbitMqBundle consumer failed');
    }
}
