<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PDODriver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;

class ConnectionTraitTest extends AbstractFunctionalTestCase
{
    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteQueryShouldNotReconnect(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 0, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $this->expectException(Exception::class);

        $connection->executeQuery('SELECT 1');
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteQueryShouldReconnect(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $connection->executeQuery('SELECT 1')->fetchAllNumeric();

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testQueryShouldReconnect(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $connection->executeQuery('SELECT 1');

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteUpdateShouldReconnect(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->createTestTable($connection);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $connection->executeStatement(self::UPDATE_QUERY);

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testExecuteStatementShouldReconnect(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->createTestTable($connection);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $connection->executeStatement(self::UPDATE_QUERY);

        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testShouldReconnectOnStatementExecuteError(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $statement = $connection->prepare("SELECT 'foo'");
        $result = $statement->executeQuery()->fetchAllAssociative();

        $this->assertSame([['foo' => 'foo']], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testShouldResetStatementOnStatementExecuteError(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $statement = $connection->prepare("SELECT 'foo', ?, ?");
        $statement->bindValue(1, 'bar');
        $param = 'baz';
        /** @psalm-suppress DeprecatedMethod */
        $statement->bindParam(2, $param);
        // change param by ref
        $param = 'baz2';

        $result = $statement->executeQuery()->fetchAllNumeric();

        $this->assertSame([['foo', 'bar', $param]], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testBindParamShouldRespectTypeWhenRecreatingStatement(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);

        $statement = $connection->prepare("SELECT 'foo', ?, ?");
        $statement->bindValue(1, 'bar');
        $param = 1;
        /** @psalm-suppress DeprecatedMethod */
        $statement->bindParam(2, $param, ParameterType::INTEGER);
        // change param by ref
        $param = 2;
        if (PDODriver::class === $driver && PHP_VERSION_ID < 8_01_00) {
            // PDO driver before PHP 8.1 returns result always as string, ignoring parameter type
            $param = (string) $param;
        }

        $this->forceDisconnect($connection, $withTimeout);
        $result = $statement->executeQuery()->fetchAllNumeric();

        $this->assertSame([['foo', 'bar', $param]], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testShouldReconnectOnStatementFetchAllAssociative(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $statement = $connection->prepare("SELECT 'foo'");
        $result = $statement->executeQuery()->fetchAllAssociative();

        $this->assertSame([['foo' => 'foo']], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testShouldReconnectOnStatementFetchAllNumeric(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $statement = $connection->prepare("SELECT 'foo'");
        $result = $statement->executeQuery()->fetchAllNumeric();

        $this->assertSame([['0' => 'foo']], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testBeginTransactionShouldNotReconnectIfNested(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);

        $connection->beginTransaction();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $this->expectExceptionMessage('MySQL server has gone away');

        $connection->beginTransaction();

        if ($enableSavepoints) {
            $this->fail('With savepoints enabled, test should fail without having to trigger a further query');
        }

        $connection->executeStatement('SELECT 1');
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testBeginTransactionShouldNotReconnect(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 0, $enableSavepoints);
        $driver = $connection->getDriver();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        if ($driver instanceof \Doctrine\DBAL\Driver\PDO\MySQL\Driver) {
            $this->expectException(\PDOException::class);
            $this->expectExceptionMessage('MySQL server has gone away');
        }

        $connection->beginTransaction();
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testBeginTransactionShouldReconnect(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $driver = $connection->getDriver();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection, $withTimeout);

        $connection->beginTransaction();

        if ($driver instanceof \Doctrine\DBAL\Driver\PDO\MySQL\Driver) {
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
    public function testShouldReconnectOnExecutePreparedStatement(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection, $withTimeout);

        $this->assertSame(1, $statement->executeStatement());
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testShouldReconnectOnExecuteQueryPreparedStatement(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection, $withTimeout);

        $this->assertEquals([[1 => '1']], $statement->executeQuery()->fetchAllAssociative());
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param class-string<Driver> $driver
     */
    #[DataProvider('driverDataProvider')]
    public function testShouldNotReconnectOnBrokenTransaction(string $driver, bool $enableSavepoints, bool $withTimeout): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);

        $this->assertTrue($connection->beginTransaction());
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection, $withTimeout);

        $this->expectException(ConnectionLost::class);
        $statement->executeQuery();
    }
}
