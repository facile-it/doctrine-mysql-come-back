<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

use Doctrine\DBAL\Driver\Mysqli\Driver;
use Doctrine\DBAL\DriverManager;

class MysqliTest extends AbstractFunctionalTest
{
    protected function createConnection(int $attempts): Connection
    {
        /** @var Connection $connection */
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

        return $connection;
    }
}
