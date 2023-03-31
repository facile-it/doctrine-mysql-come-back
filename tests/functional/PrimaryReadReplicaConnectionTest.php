<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\DriverManager;

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
}
