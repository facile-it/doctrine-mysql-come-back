<?php

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\PrimaryReadReplicaConnection;

class PrimaryReadReplicaConnectionTest extends ConnectionTraitTest
{
    /**
     * @param class-string<Driver> $driver
     */
    protected function createConnection(string $driver, int $attempts): PrimaryReadReplicaConnection
    {
        $connection = DriverManager::getConnection(array_merge(
            [
                'primary' => $this->getConnectionParams(),
                'replica' => [$this->getConnectionParams()],
                'driverOptions' => [
                    'x_reconnect_attempts' => $attempts,
                ],
            ],
            [
                'wrapperClass' => PrimaryReadReplicaConnection::class,
                'driverClass' => $driver,
            ]
        ));

        $this->assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);

        return $connection;
    }

    /**
     * @param class-string<Driver> $driver
     *
     * @return Connection|PrimaryReadReplicaConnection
     */
    protected function getConnectedConnection(string $driver, int $attempts): DBALConnection
    {
        $connection = parent::getConnectedConnection($driver, $attempts);
        $this->assertInstanceOf(PrimaryReadReplicaConnection::class, $connection);
        $connection->ensureConnectedToPrimary();

        return $connection;
    }

    /**
     * @dataProvider driverDataProvider
     *
     * @param class-string<Driver> $driver
     */
    public function testBeginTransactionShouldNotInterfereWhenSwitchingToPrimary(string $driver): void
    {
        $connection = $this->createConnection($driver, 0);
        $connection->connect();

        $this->assertFalse($connection->isConnectedToPrimary());
        $this->assertSame(1, $connection->connectCount);
        $this->forceDisconnect($connection);

        $connection->beginTransaction();

        /** @psalm-suppress RedundantConditionGivenDocblockType */
        $this->assertSame(1, $connection->connectCount);
        $this->assertTrue($connection->isConnectedToPrimary());
    }
}
