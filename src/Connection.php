<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration,
    Doctrine\DBAL\Driver,
    Doctrine\Common\EventManager;

/**
 * Class Connection
 * @package Facile\DoctrineMySQLComeBack
 */
class Connection extends \Doctrine\DBAL\Connection
{
    /**
     * @var int
     */
    protected $reconnectAttempts = 0;

    /**
     * @param array $params
     * @param Driver $driver
     * @param Configuration $config
     * @param EventManager $eventManager
     */
    public function __construct(
        array $params,
        Driver $driver,
        Configuration $config = null,
        EventManager $eventManager = null
    ) {
        if (isset($params['driverOptions']['x_reconnect_attempts']) && method_exists(
                $driver,
                'getReconnectExceptions'
            )
        ) {
            // sanity check: 0 if no exceptions are available
            $reconnectExceptions = $driver->getReconnectExceptions();
            $this->reconnectAttempts = empty($reconnectExceptions) ? 0 : (int)$params['driverOptions']['x_reconnect_attempts'];
        }
        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @param $query
     * @param array $params
     * @param array $types
     * @param QueryCacheProfile $qcp
     * @return null
     * @throws \Exception
     */
    public function executeQuery($query, array $params = array(), $types = array(), QueryCacheProfile $qcp = null)
    {
        $stmt = null;
        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {
                $stmt = parent::executeQuery($query, $params, $types);
            } catch (\Exception $e) {
                if ($this->validateReconnectAttempt($e, $attempt)) {
                    $this->close();
                    $attempt++;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }
        return $stmt;
    }

    /**
     * @param \Exception $e
     * @param $attempt
     * @return bool
     */
    public function validateReconnectAttempt(\Exception $e, $attempt)
    {
        if ($this->getTransactionNestingLevel() < 1 && $this->reconnectAttempts && $attempt < $this->reconnectAttempts
        ) {
            // method_exists($this->_driver,'getReconnectExceptions') already checked in constructor
            $reconnectExceptions = $this->_driver->getReconnectExceptions();
            $message = $e->getMessage();
            if (!empty($reconnectExceptions)) {
                foreach ($reconnectExceptions as $reconnectException) {
                    if (strpos($message, $reconnectException) !== false) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @return null
     * @throws \Exception
     */
    public function query()
    {
        $stmt = null;
        $args = func_get_args();
        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {
                // max arguments is 4 -> anything is better then calling call_user_func_array()!
                switch (count($args)) {
                    case 1:
                        $stmt = parent::query($args[0]);
                        break;
                    case 2:
                        $stmt = parent::query($args[0], $args[1]);
                        break;
                    case 3:
                        $stmt = parent::query($args[0], $args[1], $args[2]);
                        break;
                    case 4:
                        $stmt = parent::query($args[0], $args[1], $args[2], $args[3]);
                        break;
                    default:
                        $stmt = parent::query();
                    // no break

                }
            } catch (\Exception $e) {
                if ($this->validateReconnectAttempt($e, $attempt)) {
                    $this->close();
                    $attempt++;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }
        return $stmt;
    }

    /**
     * @param $query
     * @param array $params
     * @param array $types
     * @return null
     * @throws \Exception
     */
    public function executeUpdate($query, array $params = array(), array $types = array())
    {
        $stmt = null;
        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {
                $stmt = parent::executeUpdate($query, $params, $types);
            } catch (\Exception $e) {
                if ($this->validateReconnectAttempt($e, $attempt)) {
                    $this->close();
                    $attempt++;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }
        return $stmt;
    }

    /**
     * @param $sql
     * @return Statement
     */
    public function prepare($sql)
    {
        return $this->prepareWrapped($sql);
    }

    /**
     * @param $sql
     * @return Statement
     */
    protected function prepareWrapped($sql)
    {
        // returns a reconnect-wrapper for Statements
        return new Statement($sql, $this);
    }

    /**
     * do not use, only used by Statement-class
     *
     * needs to be public for access from the Statement-class
     *
     * @deprecated
     */
    public function prepareUnwrapped($sql)
    {
        // returns the actual statement
        return parent::prepare($sql);
    }
}