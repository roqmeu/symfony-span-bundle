<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Command;

use OldSound\RabbitMqBundle\RabbitMq\Consumer;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RabbitMqBundleFailCommand extends Command
{
    private Producer $producer;
    private Consumer $consumer;

    public function __construct(Producer $producer, Consumer $consumer)
    {
        parent::__construct('app:test:rabbitmq-bundle-fail');

        $this->producer = $producer;
        $this->consumer = $consumer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Producer не знает о queue/bindings; чтобы сообщение не потерялось, заранее создаём fabric консюмера.
        $this->consumer->setupFabric();

        $this->producer->publish('fail');

        return Command::SUCCESS;
    }
}
