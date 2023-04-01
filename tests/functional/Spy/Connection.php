<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest\Spy;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Connection extends \Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection
{
    public int $connectCount = 0;

    /**
     * @param string|null $connectionName
     */
    public function connect($connectionName = null): bool
    {
        if (! $this->isConnected()) {
            ++$this->connectCount;
        }

        /** @psalm-suppress InternalMethod */
        return parent::connect($connectionName);
    }
}
