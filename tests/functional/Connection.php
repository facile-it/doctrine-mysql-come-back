<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\FunctionalTest;

class Connection extends \Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection
{
    public $connectCount = 0;

    public function connect()
    {
        if (! $this->isConnected()) {
            ++$this->connectCount;
        }

        return parent::connect();
    }
}
