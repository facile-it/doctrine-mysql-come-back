<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql;

/**
 * Class Driver
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql
 */
class Driver extends \Doctrine\DBAL\Driver\PDOMySql\Driver
{
    /**
     * @return array
     */
    public function getReconnectExceptions()
    {
        return array(
            'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away',
            'PDOStatement::execute(): MySQL server has gone away'
        );
    }
}