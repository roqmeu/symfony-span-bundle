<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProfilerCommand extends Command
{
    public function __construct()
    {
        parent::__construct('app:test:profiler');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->profilerLevel1();

        $this->profilerLevel2();

        $this->profilerLevel3();

        return Command::SUCCESS;
    }

    private function profilerLevel1(): void
    {
        \usleep(20_000);
        $this->profilerLevel2();
    }

    private function profilerLevel2(): void
    {
        \usleep(40_000);
        $this->profilerLevel3();
    }

    private function profilerLevel3(): void
    {
        \usleep(80_000);
    }
}
