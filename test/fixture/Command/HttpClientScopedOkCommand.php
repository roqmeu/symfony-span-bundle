<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientScopedOkCommand extends Command
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        parent::__construct('app:test:http-client-scoped-ok');

        $this->httpClient = $httpClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = $this->httpClient->request('GET', '/scoped');

        try {
            $request->getStatusCode();
        } catch (TransportExceptionInterface $e) {
        }

        return Command::SUCCESS;
    }
}
