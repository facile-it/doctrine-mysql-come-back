<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql;

use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareTrait;

/**
 * Class Driver
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql
 */
class Driver extends \Doctrine\DBAL\Driver\PDOMySql\Driver implements ServerGoneAwayExceptionsAwareInterface
{
    use ServerGoneAwayExceptionsAwareTrait;
}
