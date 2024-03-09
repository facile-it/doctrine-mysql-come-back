<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PDODriver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\DataProvider;

class ConnectionTraitTest extends AbstractFunctionalTestCase
{
    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteQueryShouldNotReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 0);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $this->expectException(Exception::class);

        $connection->executeQuery('SELECT 1');
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteQueryShouldReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $connection->executeQuery('SELECT 1')->fetchAllNumeric();

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testQueryShouldReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $connection->executeQuery('SELECT 1');

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
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
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
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
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
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
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
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
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testBindParamShouldRespectTypeWhenRecreatingStatement(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);

        $statement = $connection->prepare("SELECT 'foo', ?");
        $param = 1;
        $statement->bindValue(1, $param, ParameterType::INTEGER);

        $this->forceDisconnect($connection);
        $result = $statement->executeQuery()->fetchAllNumeric();

        $this->assertSame([['foo', $param]], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
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
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
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
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testBeginTransactionShouldNotReconnectIfNested(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);

        $connection->beginTransaction();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $this->expectExceptionMessage('MySQL server has gone away');

        $connection->beginTransaction();

        $connection->executeStatement('SELECT 1');
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testBeginTransactionShouldNotReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 0);
        $driver = $connection->getDriver();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        if ($driver instanceof PDODriver) {
            $this->expectException(Driver\PDO\Exception::class);
            $this->expectExceptionMessage('MySQL server has gone away');
        }

        $connection->beginTransaction();
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testBeginTransactionShouldReconnect(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $driver = $connection->getDriver();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $connection->beginTransaction();

        if ($driver instanceof PDODriver) {
            $this->assertConnectionCount(2, $connection);
        } else {
            $this->assertConnectionCount(1, $connection);
        }

        $this->assertSame(1, $connection->getTransactionNestingLevel());
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
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
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testShouldReconnectOnExecuteQueryPreparedStatement(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection);

        $this->assertEquals([[1 => '1']], $statement->executeQuery()->fetchAllAssociative());
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testShouldNotReconnectOnBrokenTransaction(string $driver): void
    {
        $connection = $this->getConnectedConnection($driver, 1);
        $this->assertConnectionCount(1, $connection);

        $connection->beginTransaction();
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection);

        $this->expectException(ConnectionLost::class);
        $statement->executeQuery();
    }
}
