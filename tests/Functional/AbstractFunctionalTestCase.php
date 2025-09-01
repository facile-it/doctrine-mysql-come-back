<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Mysqli\Driver as MysqliDriver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PDODriver;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Tests\DeprecationTrait;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\PrimaryReadReplicaConnection;
use PHPUnit\Framework\TestCase;

abstract class AbstractFunctionalTestCase extends TestCase
{
    use DeprecationTrait;

    protected const UPDATE_QUERY = 'UPDATE test SET updatedAt = CURRENT_TIMESTAMP WHERE id = 1';

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
        $values = [
            'driver' => getenv('MYSQL_DRIVER') ?: $GLOBALS['db_driver'] ?? 'pdo_mysql',
            'dbname' => getenv('MYSQL_DBNAME') ?: $GLOBALS['db_dbname'] ?? 'test',
            'user' => getenv('MYSQL_USER') ?: $GLOBALS['db_user'] ?? 'root',
            'password' => getenv('MYSQL_PASS') ?: $GLOBALS['db_pass'] ?? '',
            'host' => getenv('MYSQL_HOST') ?: $GLOBALS['db_host'] ?? 'localhost',
            'port' => (int) (getenv('MYSQL_PORT') ?: $GLOBALS['db_port'] ?? 3_306),
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
     * Disconnect other sessions.
     */
    protected function forceDisconnect(DBALConnection $connection, bool $withTimeout = false): void
    {
        if ($withTimeout) {
            $connection->executeQuery('SET SESSION WAIT_TIMEOUT=1');
            sleep(2);

            return;
        }

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
            $connection2->executeStatement(sprintf('KILL %d', $id));
        }
        $connection2->close();
    }

    /**
     * @return \Generator<string, array{class-string<Driver>, bool, bool}>
     */
    public static function driverDataProvider(): \Generator
    {
        $drivers = [
            'Mysqli' => MysqliDriver::class,
            'PDO' => PDODriver::class,
        ];

        $savepoints = [true, false];
        $sessionTimeouts = [false];
        if (PHP_VERSION_ID >= 8_04_00) {
            $sessionTimeouts[] = true;
        }

        foreach ($savepoints as $savepoint) {
            foreach ($drivers as $name => $driver) {
                foreach ($sessionTimeouts as $timeout) {
                    $description = sprintf(
                        '%s - %s - %s',
                        $name,
                        $savepoint ? 'with savepoint' : 'with NO savepoint',
                        $timeout ? 'with timeout' : 'with NO timeout',
                    );

                    yield $description => [$driver, $savepoint, $timeout];
                }
            }
        }
    }

    /**
     * @param Connection|PrimaryReadReplicaConnection $connection
     */
    protected function assertConnectionCount(int $expected, DBALConnection $connection): void
    {
        $this->assertTrue(
            property_exists($connection, 'connectCount'),
            'Expecting connection that implements ConnectionTraint, got ' . $connection::class
        );

        $this->assertSame($expected, $connection->connectCount);
    }
}
