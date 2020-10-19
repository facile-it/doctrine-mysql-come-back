<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\ResultStatement;
use Exception;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface;
use ReflectionClass;
use ReflectionProperty;

/**
 * Trait ConnectionTrait
 */
trait ConnectionTrait
{
    /** @var int */
    protected $reconnectAttempts = 0;

    /** @var ReflectionProperty|null */
    private $selfReflectionNestingLevelProperty;

    /**
     * @param array $params
     * @param Driver|ServerGoneAwayExceptionsAwareInterface $driver
     * @param null|Configuration $config
     * @param null|EventManager $eventManager
     *
     * @throws \InvalidArgumentException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    )
    {
        if (!$driver instanceof ServerGoneAwayExceptionsAwareInterface) {
            throw new \InvalidArgumentException(
                sprintf('%s needs a driver that implements ServerGoneAwayExceptionsAwareInterface', get_class($this))
            );
        }

        if (isset($params['driverOptions']['x_reconnect_attempts'])) {
            $this->reconnectAttempts = (int)$params['driverOptions']['x_reconnect_attempts'];
        }

        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @param string $query
     * @param array $params
     * @param array $types
     * @param QueryCacheProfile $qcp
     *
     * @return ResultStatement The executed statement.
     *
     * @throws Exception
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
            } catch (Exception $e) {
                if ($this->canTryAgain($attempt) && $this->isRetryableException($e, $query)) {
                    $this->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }

        return $stmt;
    }

    /**
     * @return \Doctrine\DBAL\Driver\Statement
     * @throws Exception
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
                $stmt = parent::query(...$args);
            } catch (Exception $e) {
                if ($this->canTryAgain($attempt) && $this->isRetryableException($e, $args[0])) {
                    $this->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }

        return $stmt;
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be used for:
     *  - DML statements: INSERT, UPDATE, DELETE, etc.
     *  - DDL statements: CREATE, DROP, ALTER, etc.
     *  - DCL statements: GRANT, REVOKE, etc.
     *  - Session control statements: ALTER SESSION, SET, DECLARE, etc.
     *  - Other statements that don't yield a row set.
     *
     * @param string                 $sql    The statement SQL
     * @param array<mixed>           $params The query parameters
     * @param array<int|string|null> $types  The parameter types
     *
     * @return int The number of affected rows.
     */
    public function executeStatement($sql, array $params = [], array $types = [])
    {
        $stmt = null;
        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {
                $stmt = parent::executeStatement($sql, $params, $types);
            } catch (Exception $e) {
                if ($this->canTryAgain($attempt) && $this->isRetryableException($e)) {
                    $this->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }

        return $stmt;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function beginTransaction()
    {
        if (0 !== $this->getTransactionNestingLevel()) {
            return parent::beginTransaction();
        }

        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {
                parent::beginTransaction();
            } catch (Exception $e) {
                if ($this->canTryAgain($attempt, true) && $this->_driver->isGoneAwayException($e)) {
                    $this->close();
                    if (0 < $this->getTransactionNestingLevel()) {
                        $this->resetTransactionNestingLevel();
                    }
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param $sql
     *
     * @return Statement
     */
    public function prepare($sql)
    {
        return $this->prepareWrapped($sql);
    }

    /**
     * returns a reconnect-wrapper for Statements.
     *
     * @param $sql
     *
     * @return Statement
     */
    protected function prepareWrapped($sql)
    {
        return new Statement($sql, $this);
    }

    /**
     * do not use, only used by Statement-class
     * needs to be public for access from the Statement-class.
     *
     * @internal
     */
    public function prepareUnwrapped($sql)
    {
        // returns the actual statement
        return parent::prepare($sql);
    }

    /**
     * Forces reconnection by doing a dummy query.
     *
     * @throws Exception
     */
    public function refresh()
    {
        $this->query('SELECT 1')->execute();
    }

    /**
     * @param $attempt
     * @param bool $ignoreTransactionLevel
     *
     * @return bool
     */
    public function canTryAgain($attempt, $ignoreTransactionLevel = false)
    {
        $canByAttempt = ($attempt < $this->reconnectAttempts);
        $canByTransactionNestingLevel = $ignoreTransactionLevel ? true : (0 === $this->getTransactionNestingLevel());

        return $canByAttempt && $canByTransactionNestingLevel;
    }

    /**
     * @param Exception $e
     * @param string|null $query
     *
     * @return bool
     */
    public function isRetryableException(Exception $e, ?string $query = null)
    {
        if (null === $query || $this->isUpdateQuery($query)) {
            return $this->_driver->isGoneAwayInUpdateException($e);
        }

        return $this->_driver->isGoneAwayException($e);
    }

    /**
     * This is required because beginTransaction increment transactionNestingLevel
     * before the real query is executed, and results incremented also on gone away error.
     * This should be safe for a new established connection.
     */
    private function resetTransactionNestingLevel()
    {
        if (!$this->selfReflectionNestingLevelProperty instanceof ReflectionProperty) {
            $reflection = new ReflectionClass(DBALConnection::class);

            // Private property has been renamed in DBAL 2.9.0+
            if ($reflection->hasProperty('transactionNestingLevel')) {
                $this->selfReflectionNestingLevelProperty = $reflection->getProperty('transactionNestingLevel');
            } else {
                $this->selfReflectionNestingLevelProperty = $reflection->getProperty('_transactionNestingLevel');
            }

            $this->selfReflectionNestingLevelProperty->setAccessible(true);
        }

        $this->selfReflectionNestingLevelProperty->setValue($this, 0);
    }

    /**
     * @param string $query
     *
     * @return bool
     */
    public function isUpdateQuery($query)
    {
        return !preg_match('/^[\s\n\r\t(]*(select|show|describe)[\s\n\r\t(]+/i', $query);
    }
}