<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver;

use Exception;

/**
 * Trait ServerGoneAwayExceptionsAwareTrait.
 */
trait ServerGoneAwayExceptionsAwareTrait
{
    /** @var string[] */
    protected $goneAwayExceptions = [
        'MySQL server has gone away',
        'Lost connection to MySQL server during query',
    ];

    /** @var string[] */
    protected $goneAwayInUpdateExceptions = [
        'MySQL server has gone away',
    ];

    /**
     * @param Exception $exception
     *
     * @return bool
     */
    public function isGoneAwayException(Exception $exception): bool
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
     * @param Exception $exception
     *
     * @return bool
     */
    public function isGoneAwayInUpdateException(Exception $exception): bool
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
