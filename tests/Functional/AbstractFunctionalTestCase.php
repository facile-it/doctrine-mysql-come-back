<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Mysqli\Driver as MysqliDriver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PDODriver;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionTrait;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\PrimaryReadReplicaConnection;
use PHPUnit\Framework\TestCase;

abstract class AbstractFunctionalTestCase extends TestCase
{
    protected const UPDATE_QUERY = 'UPDATE test SET updatedAt = CURRENT_TIMESTAMP WHERE id = 1';

    /**
     * @param class-string<Driver> $driver
     *
     * @return Connection|PrimaryReadReplicaConnection
     */
    protected function createConnection(string $driver, int $attempts): DBALConnection
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

        return $connection;
    }

    /**
     * @param class-string<Driver> $driver
     *
     * @return Connection|PrimaryReadReplicaConnection
     */
    protected function getConnectedConnection(string $driver, int $attempts): DBALConnection
    {
        $connection = $this->createConnection($driver, $attempts);
        $connection->executeQuery('SELECT 1');

        return $connection;
    }

    /**
     * @param Connection|PrimaryReadReplicaConnection $connection
     */
    protected function createTestTable(DBALConnection $connection): void
    {
        $connection->executeStatement(
            <<<'TABLE_WRAP'
CREATE TABLE IF NOT EXISTS test (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() 
);
TABLE_WRAP
        );

        $connection->executeStatement('DELETE FROM `test`;');
        $connection->executeStatement('INSERT INTO test (id) VALUES (1);');
    }

    /**
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType, RiskyTruthyFalsyComparison
     *
     * @return array{
     *     driver: 'mysqli'|'pdo_mysql',
     *     dbname: string,
     *     user: string,
     *     password: string,
     *     host: string,
     *     port: int
     * }
     */
    protected function getConnectionParams(): array
    {
        /** @var key-of<DriverManager::DRIVER_MAP> $driver */
        $driver = (string) (getenv('MYSQL_DRIVER') !== false ? getenv('MYSQL_DRIVER') : ($GLOBALS['db_driver'] ?? 'pdo_mysql'));
        if (! in_array($driver, DriverManager::getAvailableDrivers(), true)) {
            $this->fail(sprintf('Invalid driver class: %s', $driver));
        }

        $values = [
            'driver' => $driver,
            'dbname' => getenv('MYSQL_DBNAME') !== false ? getenv('MYSQL_DBNAME') : ($GLOBALS['db_dbname'] ?? 'test'),
            'user' => getenv('MYSQL_USER') !== false ? getenv('MYSQL_USER') : ($GLOBALS['db_user'] ?? 'root'),
            'password' => getenv('MYSQL_PASS') !== false ? getenv('MYSQL_PASS') : ($GLOBALS['db_pass'] ?? ''),
            'host' => getenv('MYSQL_HOST') !== false ? getenv('MYSQL_HOST') : ($GLOBALS['db_host'] ?? 'localhost'),
            'port' => (int) (getenv('MYSQL_PORT') !== false ? getenv('MYSQL_PORT') : ($GLOBALS['db_port'] ?? 3306)),
        ];

        if ($values['driver'] !== 'pdo_mysql') {
            assert($values['driver'] === 'mysqli');
        }
        $this->assertIsString($values['dbname']);
        $this->assertIsString($values['user']);
        $this->assertIsString($values['password']);
        $this->assertIsString($values['host']);

        /** @psalm-suppress LessSpecificReturnStatement */
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
     * @return array<string, array{class-string<Driver>}>
     */
    public static function driverDataProvider(): array
    {
        return [
            'Mysqli' => [MysqliDriver::class],
            'PDO' => [PDODriver::class],
        ];
    }

    /**
     * @param Connection|PrimaryReadReplicaConnection $connection
     */
    protected function assertConnectionCount(int $expected, DBALConnection $connection): void
    {
        $this->assertTrue(
            property_exists($connection, 'connectCount'),
            sprintf('Expecting connection that implements %s, got %s', ConnectionTrait::class, $connection::class)
        );

        $this->assertSame($expected, $connection->connectCount);
    }
}
