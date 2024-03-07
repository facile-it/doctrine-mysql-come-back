<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Functional\Spy;

use Doctrine\DBAL\Driver\Connection as DriverConnection;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Connection extends \Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection
{
    public int $connectCount = 0;

    /**
     * @param string|null $connectionName
     */
    public function connect(?string $connectionName = null): DriverConnection
    {
        if (! $this->isConnected()) {
            ++$this->connectCount;
        }

        /** @psalm-suppress InternalMethod */
        return parent::connect($connectionName);
    }
}
