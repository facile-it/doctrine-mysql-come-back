<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Result;

class Connection extends DBALConnection implements ConnectionInterface
{
    /** @var int */
    protected $reconnectAttempts = 0;

    /** @var \ReflectionProperty|null */
    private $selfReflectionNestingLevelProperty;

    /** @var int */
    protected $defaultFetchMode = FetchMode::ASSOCIATIVE;

    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    ) {
        if (isset($params['driverOptions']['x_reconnect_attempts'])) {
            $this->reconnectAttempts = (int) $params['driverOptions']['x_reconnect_attempts'];
        }

        parent::__construct($params, $driver, $config, $eventManager);
    }

    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $attempt = 0;

        do {
            $retry = false;
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

    public function executeStatement($sql, array $params = [], array $types = [])
    {
        $attempt = 0;

        do {
            $retry = false;
            try {
                return parent::executeStatement($sql, $params, $types);
            } catch (Exception $e) {
                if ($this->canTryAgain($attempt) && $this->isRetryableException($e)) {
                    $this->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        } while ($retry);
    }

    public function beginTransaction()
    {
        if (0 !== $this->getTransactionNestingLevel()) {
            return parent::beginTransaction();
        }

        $attempt = 0;

        do {
            $retry = false;
            try {
                return parent::beginTransaction();
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
        } while ($retry);
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
    private function resetTransactionNestingLevel(): void
    {
        if (! $this->selfReflectionNestingLevelProperty instanceof \ReflectionProperty) {
            $reflection = new \ReflectionClass(DBALConnection::class);
            $this->selfReflectionNestingLevelProperty = $reflection->getProperty('transactionNestingLevel');
        }

        $this->selfReflectionNestingLevelProperty->setAccessible(true);
        $this->selfReflectionNestingLevelProperty->setValue($this, 0);
        $this->selfReflectionNestingLevelProperty->setAccessible(false);
    }

    public function isUpdateQuery(string $sql): bool
    {
        return ! preg_match('/^[\s\n\r\t(]*(select|show|describe)[\s\n\r\t(]+/i', $sql);
    }
}
