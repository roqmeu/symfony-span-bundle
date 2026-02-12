<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HttpClientStreamOkCommand extends Command
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        parent::__construct('app:test:http-client-stream-ok');

        $this->httpClient = $httpClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ResponseInterface[] $responses */
        $responses = [
            $this->httpClient->request('GET', 'http://span-bundle.lan'),
            $this->httpClient->request('GET', 'http://span-bundle.lan'),
        ];

        foreach ($this->httpClient->stream($responses) as $response => $chunk) {
            try {
                if ($chunk->isTimeout()) {
                    $response->cancel();
                }
            } catch (TransportExceptionInterface $e) {
            }
        }

        return Command::SUCCESS;
    }
}
