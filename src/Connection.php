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
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\GoneAwayDetector;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\MySQLGoneAwayDetector;

class Connection extends DBALConnection
{
    /** @var GoneAwayDetector */
    protected $goneAwayDetector;

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
            unset($params['driverOptions']['x_reconnect_attempts']);
        }

        $this->goneAwayDetector = new MySQLGoneAwayDetector();

        parent::__construct($params, $driver, $config, $eventManager);
    }

    public function setGoneAwayDetector(GoneAwayDetector $goneAwayDetector): void
    {
        $this->goneAwayDetector = $goneAwayDetector;
    }

    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $attempt = 0;

        do {
            $retry = false;
            try {
                return @parent::executeQuery($sql, $params, $types, $qcp);
            } catch (Exception $e) {
                if ($this->canTryAgain($e, $attempt, $sql)) {
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
                return @parent::executeStatement($sql, $params, $types);
            } catch (Exception $e) {
                if ($this->canTryAgain($e, $attempt, $sql)) {
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
            return @parent::beginTransaction();
        }

        $attempt = 0;

        do {
            $retry = false;
            try {
                return @parent::beginTransaction();
            } catch (Exception $e) {
                if ($this->canTryAgain($e, $attempt)) {
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

    public function canTryAgain(\Throwable $throwable, int $attempt, string $sql = null, bool $ignoreTransactionLevel = false): bool
    {
        if ($attempt >= $this->reconnectAttempts) {
            return false;
        }

        if (! $ignoreTransactionLevel && $this->getTransactionNestingLevel() > 0) {
            return false;
        }

        return $this->goneAwayDetector->isGoneAwayException($throwable, $sql);
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
}
