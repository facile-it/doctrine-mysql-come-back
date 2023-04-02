<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional;

use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection;

class PDOMySqlTest extends AbstractFunctionalTestCase
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
