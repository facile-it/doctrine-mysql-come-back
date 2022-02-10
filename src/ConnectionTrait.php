<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Result as ResultInterface;
use Doctrine\DBAL\Statement;
use Exception;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface;
use InvalidArgumentException;
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

    /** @var int */
    protected $defaultFetchMode = FetchMode::ASSOCIATIVE;

    /**
     * @param array $params
     * @param Driver|ServerGoneAwayExceptionsAwareInterface $driver
     * @param null|Configuration $config
     * @param null|EventManager $eventManager
     *
     * @throws InvalidArgumentException
     * @throws \Doctrine\DBAL\Exception
     */
    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    ) {
        if (! $driver instanceof ServerGoneAwayExceptionsAwareInterface) {
            throw new InvalidArgumentException(
                sprintf('%s needs a driver that implements ServerGoneAwayExceptionsAwareInterface', get_class($this))
            );
        }

        if (isset($params['driverOptions']['x_reconnect_attempts'])) {
            $this->reconnectAttempts = (int) $params['driverOptions']['x_reconnect_attempts'];
        }

        parent::__construct($params, $driver, $config, $eventManager);
    }

    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $attempt = 0;
        $retry = false;

        do {
            try {
                return parent::executeQuery($sql, $params, $types, $qcp);
            } catch (Exception $e) {
                if ($this->canTryAgain($attempt) && $this->isRetryableException($e, $sql)) {
                    $this->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        } while ($retry);
    }

    /**
     * @inheritDoc
     */
    public function query(string $sql): ResultInterface
    {
        $attempt = 0;

        do {
            try {
                return parent::query($sql);
            } catch (Exception $e) {
                if ($this->canTryAgain($attempt) && $this->isRetryableException($e, $sql)) {
                    $this->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        } while ($retry);
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
     * @return int the number of affected rows
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
     * @throws Exception
     *
     * @return void
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

    public function prepare(string $sql): StatementInterface
    {
        return $this->prepareWrapped($sql);
    }

    /**
     * Returns a reconnect-wrapper for Statements.
     */
    protected function prepareWrapped(string $sql): StatementInterface
    {
        $stmt = new Statement($this, $TODO, $sql);
        $stmt->setFetchMode($this->defaultFetchMode);

        return $stmt;
    }

    /**
     * Forces reconnection by doing a dummy query.
     *
     * @deprecated Use ping()
     *
     * @throws Exception
     */
    public function refresh()
    {
        $this->query('SELECT 1')->execute();
    }

    /**
     * @param int $attempt
     * @param bool $ignoreTransactionLevel
     *
     * @return bool
     */
    public function canTryAgain($attempt, $ignoreTransactionLevel = false)
    {
        $canByAttempt = ($attempt < $this->reconnectAttempts);
        $canByTransactionNestingLevel = $ignoreTransactionLevel || 0 === $this->getTransactionNestingLevel();

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
        if (! $this->selfReflectionNestingLevelProperty instanceof ReflectionProperty) {
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
        return ! preg_match('/^[\s\n\r\t(]*(select|show|describe)[\s\n\r\t(]+/i', $query);
    }
}
