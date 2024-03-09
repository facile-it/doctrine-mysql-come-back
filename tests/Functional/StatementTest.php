<?php

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Driver;
use PHPUnit\Framework\Attributes\DataProvider;

class StatementTest extends AbstractFunctionalTestCase
{
    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testRetriesShouldNotRetryConnection(string $driver): void
    {
        $connection = $this->createConnection($driver, 1);
        $statement = $connection->prepare('SELECT 1');
        $this->forceDisconnect($connection);

        $this->assertEquals([[1]], $statement->executeQuery()->fetchAllNumeric());

        $this->forceDisconnect($connection);

        // attempts counter should be reset, so it should reconnect fine now
        $this->assertEquals([[1]], $statement->executeQuery()->fetchAllNumeric());
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteQueryWithDeprecatedPassingParams(string $driver): void
    {
        $connection = $this->createConnection($driver, 1);
        $statement = $connection->prepare('SELECT ?, ?');
        $statement->bindValue(1, 'foo');
        $statement->bindValue(2, 'bar');

        $result = $statement->executeQuery();

        $this->assertEquals([['foo', 'bar']], $result->fetchAllNumeric());
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteStatementWithDeprecatedPassingParams(string $driver): void
    {
        $connection = $this->createConnection($driver, 1);
        $statement = $connection->prepare('SELECT ?, ?');

        $statement->bindValue(1, 'foo');
        $statement->bindValue(2, 'bar');

        $result = $statement->executeStatement();

        $this->assertEquals(1, $result);
    }
}
