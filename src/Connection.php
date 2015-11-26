<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Configuration,
    Doctrine\DBAL\Driver,
    Doctrine\Common\EventManager,
    Doctrine\DBAL\Cache\QueryCacheProfile;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\PDOMySql\ServerGoneAwayExceptionsAwareInterface;

/**
 * Class Connection
 *
 * @package Facile\DoctrineMySQLComeBack
 */
class Connection extends \Doctrine\DBAL\Connection
{
    /**
     * @var int
     */
    protected $reconnectAttempts = 0;

    /**
     * @var \ReflectionProperty|null
     */
    private $selfReflectionNestingLevelProperty;

    /**
     * @param array $params
     * @param Driver|ServerGoneAwayExceptionsAwareInterface $driver
     * @param Configuration $config
     * @param EventManager $eventManager
     */
    public function __construct(
        array $params,
        Driver $driver,
        Configuration $config = null,
        EventManager $eventManager = null
    )
    {
        if (
            $driver instanceof ServerGoneAwayExceptionsAwareInterface &&
            isset($params['driverOptions']['x_reconnect_attempts'])
        ) {
            $this->reconnectAttempts = (int)$params['driverOptions']['x_reconnect_attempts'];
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
                $stmt = parent::executeQuery($query, $params, $types, $qcp);
            } catch (\Exception $e) {
                if ($this->canTryAgain($attempt) && $this->_driver->isGoneAwayException($e)) {
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
                }
            } catch (\Exception $e) {
                if ($this->canTryAgain($attempt) && $this->_driver->isGoneAwayException($e)) {
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
                if ($this->canTryAgain($attempt) && $this->_driver->isGoneAwayException($e)) {
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
     * @throws \Exception
     */
    public function beginTransaction()
    {
        if (0 !== $this->getTransactionNestingLevel()) {
           return parent::beginTransaction();
        }

        $queryResult = null;
        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {

                $queryResult = parent::beginTransaction();

            } catch (\Exception $e) {

                if ($this->canTryAgain($attempt,true) && $this->_driver->isGoneAwayException($e)) {
                    $this->close();
                    if(0 < $this->getTransactionNestingLevel()) {
                        $this->resetTransactionNestingLevel();
                    }
                    $attempt++;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }

        return $queryResult;
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
     * returns a reconnect-wrapper for Statements
     *
     * @param $sql
     * @return Statement
     */
    protected function prepareWrapped($sql)
    {
        return new Statement($sql, $this);
    }

    /**
     * do not use, only used by Statement-class
     * needs to be public for access from the Statement-class
     *
     * @internal
     */
    public function prepareUnwrapped($sql)
    {
        // returns the actual statement
        return parent::prepare($sql);
    }

    /**
     * Forces reconnection by doing a dummy query
     *
     * @throws \Exception
     */
    public function refresh()
    {
        $this->query('SELECT 1')->execute();
    }

    /**
     * @param $attempt
     * @param bool $ignoreTransactionLevel
     * @return bool
     */
    public function canTryAgain($attempt, $ignoreTransactionLevel = false)
    {
        $canByAttempt = ($attempt < $this->reconnectAttempts);
        $canByTransactionNestingLevel = $ignoreTransactionLevel ? true : (0 === $this->getTransactionNestingLevel());

        return ($canByAttempt && $canByTransactionNestingLevel);
    }

    /**
     * This is required because beginTransaction increment _transactionNestingLevel
     * before the real query is executed, and results incremented also on gone away error.
     * This should be safe for a new established connection.
     */
    private function resetTransactionNestingLevel()
    {
        if(!$this->selfReflectionNestingLevelProperty instanceof \ReflectionProperty) {
            $reflection = new \ReflectionClass(self::class);
            $this->selfReflectionNestingLevelProperty = $reflection->getProperty("_transactionNestingLevel");
        }

        $this->selfReflectionNestingLevelProperty->setValue($this,0);
    }
}
