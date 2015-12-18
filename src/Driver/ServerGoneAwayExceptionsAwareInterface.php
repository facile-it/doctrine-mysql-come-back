<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver;

/**
 * Class ServerGoneAwayExceptionsAwareInterface
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver
 */
interface ServerGoneAwayExceptionsAwareInterface
{
    /**
     * @param \Exception $e
     * @return bool
     */
    function isGoneAwayException(\Exception $e);
}
