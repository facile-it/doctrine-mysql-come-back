<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional\Spy;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class PrimaryReadReplicaConnection extends \Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\PrimaryReadReplicaConnection
{
    public int $connectCount = 0;

    protected function connectTo($connectionName)
    {
        if (! $this->isConnected()) {
            ++$this->connectCount;
        }

        return parent::connectTo($connectionName);
    }
}
