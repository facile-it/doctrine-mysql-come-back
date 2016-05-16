<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\Mysqli;

use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface,
    Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareTrait;

/**
 * Class Driver
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\Mysqli
 */
class Driver extends \Doctrine\DBAL\Driver\Mysqli\Driver implements ServerGoneAwayExceptionsAwareInterface
{
    use ServerGoneAwayExceptionsAwareTrait;

    /**
     * @var array
     */
    private $extendedDriverOptions = [
        "x_reconnect_attempts",
    ];

    /**
     * {@inheritdoc}
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        $driverOptions = array_diff_key($driverOptions, array_flip($this->extendedDriverOptions));

        parent::connect($params, $username, $password, $driverOptions);
    }
}
