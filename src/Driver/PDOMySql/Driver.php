<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql;

use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface;

/**
 * Class Driver
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql
 */
class Driver extends \Doctrine\DBAL\Driver\PDOMySql\Driver implements ServerGoneAwayExceptionsAwareInterface
{
    /**
     * @param \Exception $exception
     * @return bool
     */
    public function isGoneAwayException(\Exception $exception)
    {
        $message = $exception->getMessage();
        if (strpos($message, 'MySQL server has gone away') !== false) {
            return true;
        }

        return false;
    }
}
