<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

use Doctrine\DBAL\DriverManager;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql\Driver;

class PDOMySqlTest extends AbstractFunctionalTest
{
    protected function createConnection(int $attempts, int $delay): Connection
    {
        /** @var Connection $connection */
        $connection = DriverManager::getConnection(array_merge(
            $this->getConnectionParams(),
            [
                'wrapperClass' => Connection::class,
                'driverClass' => Driver::class,
                'driverOptions' => array(
                    'x_reconnect_attempts' => $attempts,
                    'x_reconnect_delay' => $delay,
                )
            ]
        ));

        return $connection;
    }
}
