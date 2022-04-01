<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

use Doctrine\DBAL\Connection;

class TestConnection extends Connection
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
