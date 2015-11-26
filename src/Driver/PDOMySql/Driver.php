<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql;

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
        'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away',
        'PDOStatement::execute(): MySQL server has gone away'
    );

    /**
     * @param \Exception $exception
     * @return bool
     */
    public function isGoneAwayException(\Exception $exception)
    {
        $message = $exception->getMessage();

        foreach ($this->goneAwayExceptions as $goneAwayException) {
            if (strpos($message, $goneAwayException) !== false) {
                return true;
            }
        }

        return false;
    }
}
