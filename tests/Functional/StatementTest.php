<?php

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Doctrine\DBAL\Driver;
use Facile\DoctrineMySQLComeBack\Tests\DeprecationTrait;

class StatementTest extends AbstractFunctionalTestCase
{
    use DeprecationTrait;

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testRetriesShouldNotRetryConnection(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->createConnection($driver, 1, $enableSavepoints);
        $statement = $connection->prepare('SELECT 1');
        $this->forceDisconnect($connection, $withTimeout);

        $this->assertEquals([[1]], $statement->executeQuery()->fetchAllNumeric());

        $this->forceDisconnect($connection, $withTimeout);

        // attempts counter should be reset, so it should reconnect fine now
        $this->assertEquals([[1]], $statement->executeQuery()->fetchAllNumeric());
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteQueryWithDeprecatedPassingParams(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->createConnection($driver, 1, $enableSavepoints);
        $statement = $connection->prepare('SELECT ?, ?');

        $result = $statement->executeQuery(['foo', 'bar']);

        $this->assertEquals([['foo', 'bar']], $result->fetchAllNumeric());
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteStatementWithDeprecatedPassingParams(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->createConnection($driver, 1, $enableSavepoints);
        $statement = $connection->prepare('SELECT ?, ?');

        $result = $statement->executeStatement(['foo', 'bar']);

        $this->assertEquals(1, $result);
    }
}
