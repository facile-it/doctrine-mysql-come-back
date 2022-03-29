<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

class Connection extends \Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection
{
    /** @var int */
    public $connectCount = 0;

    public function connect(): bool
    {
        if (! $this->isConnected()) {
            ++$this->connectCount;
        }

        /** @psalm-suppress InternalMethod */
        return parent::connect();
    }
}
