<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest\Spy;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class PrimaryReadReplicaConnection extends \Facile\DoctrineMySQLComeBack\Doctrine\DBAL\PrimaryReadReplicaConnection
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
