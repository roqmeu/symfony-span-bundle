<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GuzzleOkCommand extends Command
{
    private Client $client;

    public function __construct(Client $client)
    {
        parent::__construct('app:test:guzzle-ok');

        $this->client = $client;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->client->request('GET', 'http://span-bundle.lan');
        } catch (\Throwable $e) {
        }

        return Command::SUCCESS;
    }
}

