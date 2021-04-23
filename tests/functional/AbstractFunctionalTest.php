<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use PHPUnit\Framework\TestCase;

abstract class AbstractFunctionalTest extends TestCase
{
    abstract protected function createConnection(int $attempts): Connection;
    
    protected function getConnectedConnection(int $attempts): Connection
    {
        $connection = $this->createConnection($attempts);
        $connection->query('SELECT 1');
        
        return $connection;
    }

    protected function createTestTable(Connection $connection): void
    {
        $connection->executeStatement(<<<'TABLE'
CREATE TABLE IF NOT EXISTS test (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() 
);
TABLE
        );

        $connection->executeStatement('DELETE FROM `test`;');
        $connection->executeStatement('INSERT INTO test (id) VALUES (1);');
    }

    protected function getConnectionParams(): array
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

    /**
     * Disconnect other sessions
     */
    protected function forceDisconnect(\Doctrine\DBAL\Connection $connection): void
    {
        /** @var Connection $connection */
        $connection2 = DriverManager::getConnection(array_merge(
            $this->getConnectionParams(),
            [
                'wrapperClass' => Connection::class,
                'driverClass' => Driver::class,
                'driverOptions' => array(
                    'x_reconnect_attempts' => 1
                )
            ]
        ));

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

        $connection->executeQuery('SELECT 1')->fetch();
        $this->assertSame(2, $connection->connectCount);
    }

    public function testQueryShouldReconnect(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->query('SELECT 1')->execute();
        $this->assertSame(2, $connection->connectCount);
    }

    public function testExecuteUpdateShouldReconnect(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->createTestTable($connection);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->executeUpdate('UPDATE test SET updatedAt = CURRENT_TIMESTAMP WHERE id = 1');
        $this->assertSame(2, $connection->connectCount);
    }

    public function testExecuteStatementShouldReconnect(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->createTestTable($connection);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->executeStatement('UPDATE test SET updatedAt = CURRENT_TIMESTAMP WHERE id = 1');
        $this->assertSame(2, $connection->connectCount);
    }

    public function testShouldReconnectOnStatementExecuteError(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo'");
        $statement->execute();
        $result = $statement->fetchAll();
        $this->assertSame([['foo' => 'foo']], $result);
        $this->assertSame(2, $connection->connectCount);
    }

    public function testShouldResetStatementOnStatementExecuteError(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo', ?, ?, ?, ?");
        $statement->setFetchMode(\PDO::FETCH_NUM);
        $statement->bindValue(1, 2);
        $statement->bindValue(2, 'fooB');
        $statement->bindValue(3, 'fooC');
        $param1 = 5;
        $statement->bindParam(4, $param1);
        $statement->execute();
        $result = $statement->fetchAll();
        $this->assertSame([[
            0 => 'foo',
            1 => '2',
            2 => 'fooB',
            3 => 'fooC',
            4 => '5',
        ]], $result);
        $this->assertSame(2, $connection->connectCount);
    }

    public function testShouldReconnectOnStatementFetchAllAssociative(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo'");
        $statement->execute();
        $result = $statement->fetchAllAssociative();
        $this->assertSame([['foo' => 'foo']], $result);
        $this->assertSame(2, $connection->connectCount);
    }

    public function testShouldReconnectOnStatementFetchAllNumeric(): void
    {
        $connection = $this->getConnectedConnection(1);
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $statement = $connection->prepare("SELECT 'foo'");
        $statement->execute();
        $result = $statement->fetchAllNumeric();
        $this->assertSame([['0' => 'foo']], $result);
        $this->assertSame(2, $connection->connectCount);
    }
}
