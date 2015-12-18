<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql;

/**
 * Class ServerGoneAwayExceptionsAwareInterface
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql
 */
interface ServerGoneAwayExceptionsAwareInterface
{
    /**
     * @param \Exception $e
     * @return bool
     */
    function isGoneAwayException(\Exception $e);
}
