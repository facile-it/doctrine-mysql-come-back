<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\PrimaryReadReplicaConnection;
use PHPUnit\Framework\TestCase;

abstract class AbstractFunctionalTestCase extends TestCase
{
    private const UPDATE_QUERY = 'UPDATE test SET updatedAt = CURRENT_TIMESTAMP WHERE id = 1';

    /**
     * @return Connection|PrimaryReadReplicaConnection
     */
    abstract protected function createConnection(int $attempts): DBALConnection;

    /**
     * @return Connection|PrimaryReadReplicaConnection
     */
    protected function getConnectedConnection(int $attempts): DBALConnection
    {
        $connection = $this->createConnection($attempts);
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
                'driverClass' => Driver::class,
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

    public function testExecuteQueryShouldNotReconnect(): void
    {
        $connection = $this->getConnectedConnection(0);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $this->expectException(Exception::class);

        $connection->executeQuery('SELECT 1');
    }

    public function testExecuteQueryShouldReconnect(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->executeQuery('SELECT 1')->fetchAllNumeric();

        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }

    public function testQueryShouldReconnect(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->executeQuery('SELECT 1');

        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }

    public function testExecuteUpdateShouldReconnect(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->createTestTable($connection);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->executeStatement(self::UPDATE_QUERY);

        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }

    public function testExecuteStatementShouldReconnect(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->createTestTable($connection);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->executeStatement(self::UPDATE_QUERY);

        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }

    public function testShouldReconnectOnStatementExecuteError(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo'");
        $result = $statement->executeQuery()->fetchAllAssociative();

        $this->assertSame([['foo' => 'foo']], $result);
        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }

    public function testShouldResetStatementOnStatementExecuteError(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo', ?, ?, ?, ?");
        $params = [
            '2',
            'fooB',
            'fooC',
            '5',
        ];

        $result = $statement->executeQuery($params)->fetchAllNumeric();

        array_unshift($params, 'foo');
        $this->assertSame([$params], $result);
        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }

    public function testShouldReconnectOnStatementFetchAllAssociative(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo'");
        $result = $statement->executeQuery()->fetchAllAssociative();

        $this->assertSame([['foo' => 'foo']], $result);
        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }

    public function testShouldReconnectOnStatementFetchAllNumeric(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo'");
        $result = $statement->executeQuery()->fetchAllNumeric();

        $this->assertSame([['0' => 'foo']], $result);
        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }

    public function testBeginTransactionShouldNotReconnect(): void
    {
        $connection = $this->getConnectedConnection(0);
        $driver = $connection->getDriver();
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        if (is_a($driver, Driver::class)) {
            $this->expectException(\PDOException::class);
            $this->expectExceptionMessage('MySQL server has gone away');
        }

        $connection->beginTransaction();
    }

    public function testBeginTransactionShouldReconnect(): void
    {
        $connection = $this->getConnectedConnection(1);
        $driver = $connection->getDriver();
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->beginTransaction();

        if (is_a($driver, Driver::class)) {
            /** @psalm-suppress DocblockTypeContradiction */
            $this->assertSame(2, $connection->connectCount);
        } else {
            /** @psalm-suppress RedundantConditionGivenDocblockType */
            $this->assertSame(1, $connection->connectCount);
        }

        $this->assertSame(1, $connection->getTransactionNestingLevel());
    }

    public function testShouldReconnectOnExecutePreparedStatement(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection);

        $this->assertSame(1, $statement->executeStatement());
        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }

    public function testShouldReconnectOnExecuteQueryPreparedStatement(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $statement = $connection->prepare('SELECT 1');

        $this->forceDisconnect($connection);

        $this->assertEquals([[1 => '1']], $statement->executeQuery()->fetchAllAssociative());
        /** @psalm-suppress DocblockTypeContradiction */
        $this->assertSame(2, $connection->connectCount);
    }
}
