<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Mysqli\Driver as MysqliDriver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PDODriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Facile\DoctrineMySQLComeBack\Tests\DeprecationTrait;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\PrimaryReadReplicaConnection;
use PHPUnit\Framework\TestCase;

abstract class AbstractFunctionalTestCase extends TestCase
{
    private const UPDATE_QUERY = 'UPDATE test SET updatedAt = CURRENT_TIMESTAMP WHERE id = 1';

    use DeprecationTrait;

    /**
     * @param class-string<Driver> $driver
     *
     * @return Connection|PrimaryReadReplicaConnection
     */
    protected function createConnection(string $driver, int $attempts, bool $enableSavepoints): DBALConnection
    {
        $connection = DriverManager::getConnection(array_merge(
            $this->getConnectionParams(),
            [
                'wrapperClass' => Connection::class,
                'driverClass' => $driver,
                'driverOptions' => [
                    'x_reconnect_attempts' => $attempts,
                ],
            ]
        ));

        $this->assertInstanceOf(Connection::class, $connection);
        $connection->setNestTransactionsWithSavepoints($enableSavepoints);

        return $connection;
    }

    /**
     * @param class-string<Driver> $driver
     *
     * @return Connection|PrimaryReadReplicaConnection
     */
    protected function getConnectedConnection(string $driver, int $attempts, bool $enableSavepoints): DBALConnection
    {
        $connection = $this->createConnection($driver, $attempts, $enableSavepoints);
        $connection->executeQuery('SELECT 1');

        return $connection;
    }

    /**
     * @param Connection|PrimaryReadReplicaConnection $connection
     */
    protected function createTestTable(DBALConnection $connection): void
    {
        $connection->executeStatement(
            <<<'TABLE'
CREATE TABLE IF NOT EXISTS test (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() 
);
TABLE
        );

        $connection->executeStatement('DELETE FROM `test`;');
        $connection->executeStatement('INSERT INTO test (id) VALUES (1);');
    }

    /**
     * @return array{
     *     driver: key-of<DriverManager::DRIVER_MAP>,
     *     dbname: string,
     *     user: string,
     *     password: string,
     *     host: string,
     *     port: int
     * }
     */
    protected function getConnectionParams(): array
    {
        $values = [
            'driver' => getenv('MYSQL_DRIVER') ?: $GLOBALS['db_driver'] ?? 'pdo_mysql',
            'dbname' => getenv('MYSQL_DBNAME') ?: $GLOBALS['db_dbname'] ?? 'test',
            'user' => getenv('MYSQL_USER') ?: $GLOBALS['db_user'] ?? 'root',
            'password' => getenv('MYSQL_PASS') ?: $GLOBALS['db_pass'] ?? '',
            'host' => getenv('MYSQL_HOST') ?: $GLOBALS['db_host'] ?? 'localhost',
            'port' => (int) (getenv('MYSQL_PORT') ?: $GLOBALS['db_port'] ?? 3306),
        ];

        $this->assertIsString($values['driver']);
        if ($values['driver'] !== 'pdo_mysql') {
            assert($values['driver'] === 'mysqli');
        }
        $this->assertIsString($values['dbname']);
        $this->assertIsString($values['user']);
        $this->assertIsString($values['password']);
        $this->assertIsString($values['host']);

        return $values;
    }

    /**
     * Disconnect other sessions
     */
    protected function forceDisconnect(DBALConnection $connection): void
    {
        $connection2 = DriverManager::getConnection(array_merge(
            $this->getConnectionParams(),
            [
                'wrapperClass' => Connection::class,
                'driverClass' => PDODriver::class,
                'driverOptions' => [
                    'x_reconnect_attempts' => 0,
                ],
            ]
        ));

        /** @var list<numeric-string|int> $ids */
        $ids = $connection->fetchFirstColumn('SELECT CONNECTION_ID()');

        foreach ($ids as $id) {
            $connection2->executeStatement('KILL ' . $id);
        }
        $connection2->close();
    }

    /**
     * @return array<string, array{class-string<Driver>, bool}>
     */
    public static function driverDataProvider(): array
    {
        return [
            'Mysqli with savepoints' => [MysqliDriver::class, true],
            'Mysqli with no savepoints' => [MysqliDriver::class, false],
            'PDO with savepoints' => [PDODriver::class, true],
            'PDO with no savepoints' => [PDODriver::class, false],
        ];
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testExecuteQueryShouldNotReconnect(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 0, $enableSavepoints);
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
    public function testExecuteQueryShouldReconnect(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testQueryShouldReconnect(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testExecuteUpdateShouldReconnect(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testExecuteStatementShouldReconnect(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testShouldReconnectOnStatementExecuteError(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testShouldResetStatementOnStatementExecuteError(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo', ?, ?");
        $statement->bindValue(1, 'bar');
        $param = 'baz';
        /** @psalm-suppress DeprecatedMethod */
        $statement->bindParam(2, $param);
        // TODO - change param by ref
        //        $param = 'baz2';

        $result = $statement->executeQuery()->fetchAllNumeric();

        $this->assertSame([['foo', 'bar', 'baz']], $result);
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testShouldReconnectOnStatementFetchAllAssociative(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testShouldReconnectOnStatementFetchAllNumeric(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testBeginTransactionShouldNotReconnectIfNested(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testBeginTransactionShouldNotReconnect(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 0, $enableSavepoints);
        $driver = $connection->getDriver();
        $this->assertConnectionCount(1, $connection);
        $this->forceDisconnect($connection);

        if (is_a($driver, PDODriver::class)) {
            $this->expectException(\PDOException::class);
            $this->expectExceptionMessage('MySQL server has gone away');
        }

        $connection->beginTransaction();
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testBeginTransactionShouldReconnect(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testShouldReconnectOnExecutePreparedStatement(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
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
    public function testShouldReconnectOnExecuteQueryPreparedStatement(string $driver, bool $enableSavepoints): void
    {
        $connection = $this->getConnectedConnection($driver, 1, $enableSavepoints);
        $this->assertConnectionCount(1, $connection);
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection);

        $this->assertEquals([[1 => '1']], $statement->executeQuery()->fetchAllAssociative());
        $this->assertConnectionCount(2, $connection);
    }

    /**
     * @param Connection|PrimaryReadReplicaConnection $connection
     */
    protected function assertConnectionCount(int $expected, DBALConnection $connection): void
    {
        $this->assertTrue(
            property_exists($connection, 'connectCount'),
            'Expecting connection that implements ConnectionTraint, got ' . get_class($connection)
        );

        $this->assertSame($expected, $connection->connectCount);
    }
}
