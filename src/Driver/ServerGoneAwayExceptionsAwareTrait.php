<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver;

/**
 * Trait ServerGoneAwayExceptionsAwareTrait
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver
 */
trait ServerGoneAwayExceptionsAwareTrait
{
    /**
     * @var array
     */
    protected $goneAwayExceptions = array(
        'MySQL server has gone away',
        'Lost connection to MySQL server during query',
    );

    /**
     * @var array
     */
    protected $goneAwayInUpdateExceptions = array(
        'MySQL server has gone away',
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

    /**
     * @param \Exception $exception
     * @return bool
     */
    public function isGoneAwayInUpdateException(\Exception $exception)
    {
        $message = $exception->getMessage();

        foreach ($this->goneAwayInUpdateExceptions as $goneAwayException) {
            if (stripos($message, $goneAwayException) !== false) {
                return true;
            }
        }

        return false;
    }
}