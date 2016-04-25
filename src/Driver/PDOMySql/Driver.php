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
     * @var array
     */
    protected $goneAwayExceptions = array(
        'MySQL server has gone away',
        'Lost connection to MySQL server during query',
    );

    /**
     * @param \Exception $exception
     * @return bool
     */
    public function isGoneAwayException(\Exception $exception)
    {
        $message = $exception->getMessage();

        foreach ($this->goneAwayExceptions as $goneAwayException) {
            if (stripos($message, $goneAwayException) !== false) {
                return true;
            }
        }

        return false;
    }
}
