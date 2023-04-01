<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest\Spy\PrimaryReadReplicaConnection;

class PrimaryReadReplicaConnectionTest extends AbstractFunctionalTestCase
{
    protected function createConnection(int $attempts): PrimaryReadReplicaConnection
    {
        $connection = DriverManager::getConnection(array_merge(
            [
                'primary' => $this->getConnectionParams(),
                'replica' => [$this->getConnectionParams()],
            ],
            [
                'wrapperClass' => PrimaryReadReplicaConnection::class,
                'driverClass' => Driver::class,
                'driverOptions' => [
                    'x_reconnect_attempts' => $attempts,
                ],
            ]
        ));

        $this->assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);

        return $connection;
    }

    protected function getConnectedConnection(int $attempts): PrimaryReadReplicaConnection
    {
        $connection = parent::getConnectedConnection($attempts);
        $this->assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);
        $connection->ensureConnectedToPrimary();

        return $connection;
    }

    public function testBeginTransactionShouldNotInterfereWhenSwitchingToPrimary(): void
    {
        $connection = parent::getConnectedConnection(0);
        $this->assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);
        $this->assertFalse($connection->isConnectedToPrimary());
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->beginTransaction();

        /** @psalm-suppress RedundantConditionGivenDocblockType */
        $this->assertSame(1, $connection->connectCount);
        $this->assertTrue($connection->isConnectedToPrimary());
    }
}
