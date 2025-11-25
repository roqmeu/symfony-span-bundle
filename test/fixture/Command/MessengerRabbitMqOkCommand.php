<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Command;

use Roqmeu\SpanBundle\Test\Fixture\Messenger\OkEventRabbitMq;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MessengerRabbitMqOkCommand extends Command
{
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        parent::__construct('app:test:messenger-rabbitmq-ok');

        $this->bus = $bus;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bus->dispatch(new OkEventRabbitMq());

        return Command::SUCCESS;
    }
}
