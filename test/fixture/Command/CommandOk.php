<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandOk extends Command
{
    public function __construct()
    {
        parent::__construct('app:test:command-ok');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('command ok');

        return Command::SUCCESS;
    }
}
