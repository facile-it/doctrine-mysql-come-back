<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql\Driver;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class FunctionalTest extends TestCase
{
    private function getConnectionParams(): array
    {
        return [
            'driver' => getenv('MYSQL_DRIVER') ?: $GLOBALS['db_driver'] ?? 'pdo_mysql',
            'dbname' => getenv('MYSQL_DBNAME') ?: $GLOBALS['db_dbname'] ?? 'test',
            'user' => getenv('MYSQL_USER') ?: $GLOBALS['db_user'] ?? 'root',
            'password' => getenv('MYSQL_PASS') ?: $GLOBALS['db_pass'] ?? '',
            'host' => getenv('MYSQL_HOST') ?: $GLOBALS['db_host'] ?? 'localhost',
            'port' => (int) (getenv('MYSQL_PORT') ?: $GLOBALS['db_port'] ?? 3306),
        ];
    }

    private function createConnection(int $attempts): Connection
    {
        /** @var Connection $connection */
        $connection = DriverManager::getConnection(array_merge(
            $this->getConnectionParams(),
            [
                'wrapperClass' => Connection::class,
                'driverClass' => Driver::class,
                'driverOptions' => array(
                    'x_reconnect_attempts' => $attempts
                )
            ]
        ));

        $connection->executeStatement(<<<'TABLE'
CREATE TABLE IF NOT EXISTS test (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP  ON UPDATE CURRENT_TIMESTAMP 
);
TRUNCATE test;
INSERT INTO test (id) VALUES (1);
TABLE
        );

        return $connection;
    }

    private function setConnectionTimeout(\Doctrine\DBAL\Connection $connection, int $timeout): void
    {
        $connection->executeStatement('SET SESSION wait_timeout=' . $timeout);
        $connection->executeStatement('SET SESSION interactive_timeout=' . $timeout);
    }

    public function testExecuteQueryShouldNotReconnect(): void
    {
        $connection = $this->createConnection(0);
        $this->assertSame(1, $connection->connectCount);
        $this->setConnectionTimeout($connection, 2);
        sleep(3);
        
        $this->expectException(DBALException::class);

        $connection->executeQuery('SELECT 1');
    }

    public function testExecuteQueryShouldReconnect(): void
    {
        $connection = $this->createConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->setConnectionTimeout($connection, 2);
        sleep(3);
        $connection->executeQuery('SELECT 1')->execute();
        $this->assertSame(2, $connection->connectCount);
    }

    public function testQueryShouldReconnect(): void
    {
        $connection = $this->createConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->setConnectionTimeout($connection, 2);
        sleep(3);
        $connection->query('SELECT 1')->execute();
        $this->assertSame(2, $connection->connectCount);
    }

    public function testExecuteUpdateShouldReconnect(): void
    {
        $connection = $this->createConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->setConnectionTimeout($connection, 2);
        sleep(3);
        $connection->executeUpdate('UPDATE test SET updatedAt = CURRENT_TIMESTAMP WHERE id = 1');
        $this->assertSame(2, $connection->connectCount);
    }

    public function testExecuteStatementShouldReconnect(): void
    {
        $connection = $this->createConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->setConnectionTimeout($connection, 2);
        sleep(3);
        $connection->executeStatement('UPDATE test SET updatedAt = CURRENT_TIMESTAMP WHERE id = 1');
        $this->assertSame(2, $connection->connectCount);
    }
}
