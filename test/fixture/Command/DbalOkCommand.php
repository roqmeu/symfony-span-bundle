<?php

declare(strict_types=1);

namespace Roqmeu\SpanBundle\Test\Fixture\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DbalOkCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct('app:test:dbal-ok');

        $this->connection = $connection;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS test_users (
            id SERIAL PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL
        )
        SQL;

        $this->connection->executeStatement($sql);

        // Successful transaction
        $this->connection->beginTransaction();

        $this->connection->executeStatement(
            'INSERT INTO test_users (name, email) VALUES (?, ?)',
            ['John Doe', 'john@example.com']
        );

        $result = $this->connection->executeQuery('SELECT * FROM test_users WHERE name = ?', ['John Doe']);
        $users = $result->fetchAllAssociative();

        $this->connection->executeStatement(
            'UPDATE test_users SET email = ? WHERE name = ?',
            ['newemail@example.com', 'John Doe']
        );

        $this->connection->commit();

        // Rollback transaction
        $this->connection->beginTransaction();

        $this->connection->executeStatement(
            'INSERT INTO test_users (name, email) VALUES (?, ?)',
            ['Jane Doe', 'jane@example.com']
        );

        $this->connection->rollBack();

        $this->connection->executeStatement('DELETE FROM test_users WHERE name = ?', ['John Doe']);

        $this->connection->executeStatement('DROP TABLE IF EXISTS test_users');

        return Command::SUCCESS;
    }
}
