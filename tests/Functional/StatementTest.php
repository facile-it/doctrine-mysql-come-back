<?php

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Driver;
use Facile\DoctrineMySQLComeBack\Tests\DeprecationTrait;

class StatementTest extends AbstractFunctionalTestCase
{
    use DeprecationTrait;

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testRetriesShouldNotRetryConnection(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->createConnection($driver, 1, $enableSavepoints);
        $statement = $connection->prepare('SELECT 1');
        $this->forceDisconnect($connection);

        $this->assertEquals([[1]], $statement->executeQuery()->fetchAllNumeric());

        $this->forceDisconnect($connection);

        // attempts counter should be reset, so it should reconnect fine now
        $this->assertEquals([[1]], $statement->executeQuery()->fetchAllNumeric());
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testExecuteQueryWithDeprecatedPassingParams(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->createConnection($driver, 1, $enableSavepoints);
        $statement = $connection->prepare('SELECT ?, ?');

        $result = $statement->executeQuery(['foo', 'bar']);

        $this->assertEquals([['foo', 'bar']], $result->fetchAllNumeric());
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testExecuteStatementWithDeprecatedPassingParams(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->createConnection($driver, 1, $enableSavepoints);
        $statement = $connection->prepare('SELECT ?, ?');

        $result = $statement->executeStatement(['foo', 'bar']);

        $this->assertEquals(0, $result);
    }
}
