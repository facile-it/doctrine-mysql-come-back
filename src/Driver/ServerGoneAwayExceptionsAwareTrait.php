<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver;

/**
 * Trait ServerGoneAwayExceptionsAwareTrait.
 */
trait ServerGoneAwayExceptionsAwareTrait
{
    /**
     * @var array
     */
    protected $goneAwayExceptions = array(
        'MySQL server has gone away',
        'Lost connection to MySQL server during query',
        'The MySQL server is running with the --read-only option so it cannot execute this statement',
        'Connection refused'
    );

    /**
     * @var array
     */
    protected $goneAwayInUpdateExceptions = array(
        'MySQL server has gone away',
        'The MySQL server is running with the --read-only option so it cannot execute this statement',
        'Connection refused'
    );

    /**
     * @param \Exception $exception
     *
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
     *
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
