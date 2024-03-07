<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional\Spy;

use Doctrine\DBAL\Driver\Connection as DriverConnection;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class PrimaryReadReplicaConnection extends \Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\PrimaryReadReplicaConnection
{
    public int $connectCount = 0;

    protected function connectTo(string $connectionName): DriverConnection
    {
        if (! $this->isConnected()) {
            ++$this->connectCount;
        }

        return parent::connectTo($connectionName);
    }
}
