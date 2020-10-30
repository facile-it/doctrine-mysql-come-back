<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql;

use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareTrait;

/**
 * Class Driver.
 */
class Driver extends \Doctrine\DBAL\Driver\PDOMySql\Driver implements ServerGoneAwayExceptionsAwareInterface
{
    use ServerGoneAwayExceptionsAwareTrait;
}
