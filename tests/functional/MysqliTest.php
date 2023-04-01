<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest\Spy\Connection;

class MysqliTest extends AbstractFunctionalTestCase
{
    protected function createConnection(int $attempts): Connection
    {
        $connection = DriverManager::getConnection(array_merge(
            $this->getConnectionParams(),
            [
                'wrapperClass' => Connection::class,
                'driverClass' => Driver::class,
                'driverOptions' => [
                    'x_reconnect_attempts' => $attempts,
                ],
            ]
        ));

        $this->assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
