<?php
/**
 * Copyright (c) 2015 Nickel Media Inc.
 * Created by IntelliJ IDEA.
 * User: zackbrenton
 * Date: 2015-12-18
 * Time: 11:50 AM
 */

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\Mysqli;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface;

class Driver extends \Doctrine\DBAL\Driver\Mysqli\Driver implements ServerGoneAwayExceptionsAwareInterface
{
    /**
     * @var array
     */
    protected $goneAwayExceptions = array(
        'MySQL server has gone away'
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
