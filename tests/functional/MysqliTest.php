<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection as ConnectionUnderTest;

class MysqliTest extends AbstractFunctionalTest
{
    protected function createConnection(int $attempts): ConnectionUnderTest
    {
        $connection = DriverManager::getConnection(array_merge(
            $this->getConnectionParams(),
            [
                'wrapperClass' => ConnectionUnderTest::class,
                'x_decorated_connection_class' => TestConnection::class,
                'driverClass' => Driver::class,
                'driverOptions' => [
                    'x_reconnect_attempts' => $attempts,
                ],
            ]
        ));

        $this->assertInstanceOf(ConnectionUnderTest::class, $connection);

        return $connection;
    }
}
