<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandFail extends Command
{
    public function __construct()
    {
        parent::__construct('app:test:command-fail');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        throw new \RuntimeException('command boom');
    }
}


