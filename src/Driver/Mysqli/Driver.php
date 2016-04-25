<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\Mysqli;

use Doctrine\DBAL\DBALException,
    Doctrine\DBAL\Driver\Mysqli\MysqliConnection,
    Doctrine\DBAL\Driver\Mysqli\MysqliException;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface;

/**
 * Class Driver
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\Mysqli
 */
class Driver extends \Doctrine\DBAL\Driver\Mysqli\Driver implements ServerGoneAwayExceptionsAwareInterface
{
    /**
     * @var array
     */
    protected $goneAwayExceptions = [
        'MySQL server has gone away',
        'Lost connection to MySQL server during query',
    ];

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
        try {
            return new MysqliConnection($params, $username, $password, $driverOptions);
        } catch (MysqliException $e) {
            throw DBALException::driverException($this, $e);
        }
    }

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
