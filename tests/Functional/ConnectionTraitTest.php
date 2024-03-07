<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PDODriver;
use Doctrine\DBAL\Exception;

class ConnectionTraitTest extends AbstractFunctionalTestCase
{
    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testExecuteQueryShouldNotReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 0);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $this->expectException(Exception::class);

        $connection->executeQuery('SELECT 1');
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testExecuteQueryShouldReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $connection->executeQuery('SELECT 1')->fetchAllNumeric();

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testQueryShouldReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $connection->executeQuery('SELECT 1');

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testExecuteUpdateShouldReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->createTestTable($connection);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $connection->executeStatement(self::UPDATE_QUERY);

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testExecuteStatementShouldReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->createTestTable($connection);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $connection->executeStatement(self::UPDATE_QUERY);

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testShouldReconnectOnStatementExecuteError(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo'");
        $result = $statement->executeQuery()->fetchAllAssociative();

        $this->assertSame([['foo' => 'foo']], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testShouldResetStatementOnStatementExecuteError(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo', ?, ?");
        $statement->bindValue(1, 'bar');

        $param = 'baz';
        $statement->bindValue(2, $param);

        $result = $statement->executeQuery()->fetchAllNumeric();

        $this->assertSame([['foo', 'bar', $param]], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testShouldReconnectOnStatementFetchAllAssociative(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo'");
        $result = $statement->executeQuery()->fetchAllAssociative();

        $this->assertSame([['foo' => 'foo']], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testShouldReconnectOnStatementFetchAllNumeric(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo'");
        $result = $statement->executeQuery()->fetchAllNumeric();

        $this->assertSame([['0' => 'foo']], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testBeginTransactionShouldNotReconnectIfNested(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);

        $connection->beginTransaction();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $this->expectExceptionMessage('MySQL server has gone away');

        $connection->beginTransaction();

        if ($enableSavepoints) {
            $this->fail('With savepoints enabled, test should fail without having to trigger a further query');
        }

        $connection->executeStatement('SELECT 1');
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testBeginTransactionShouldNotReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 0);
        $driver = $connection->getDriver();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        if (is_a($driver, PDODriver::class)) {
            $this->expectException(Driver\PDO\Exception::class);
            $this->expectExceptionMessage('MySQL server has gone away');
        }

        $connection->beginTransaction();
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testBeginTransactionShouldReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $driver = $connection->getDriver();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $connection->beginTransaction();

        if (is_a($driver, PDODriver::class)) {
            $this->assertConnectionCount(2, $connection);
        } else {
            $this->assertConnectionCount(1, $connection);
        }

        $this->assertSame(1, $connection->getTransactionNestingLevel());
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testShouldReconnectOnExecutePreparedStatement(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection);

        $this->assertSame(1, $statement->executeStatement());
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testShouldReconnectOnExecuteQueryPreparedStatement(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection);

        $this->assertEquals([[1 => '1']], $statement->executeQuery()->fetchAllAssociative());
        $this->assertConnectionCount(2, $connection);
    }
}
