<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement as DBALStatement;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\GoneAwayDetector;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\MySQLGoneAwayDetector;

class Connection extends DBALConnection
{
    /** @var DBALConnection */
    private $decoratedConnection;

    /** @var GoneAwayDetector */
    protected $goneAwayDetector;

    /** @var int */
    protected $reconnectAttempts = 0;

    /** @var \ReflectionProperty|null */
    private $selfReflectionNestingLevelProperty;

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

        $decoratedConnectionClass = $params['x_decorated_connection_class'] ?? DBALConnection::class;
        unset($params['x_decorated_connection_class']);

        if (! is_string($decoratedConnectionClass)) {
            throw new \InvalidArgumentException('Expecting FQCN, got ' . get_debug_type($decoratedConnectionClass));
        }

        if (! class_exists($decoratedConnectionClass)) {
            throw new \InvalidArgumentException('Expecting FQCN, got ' . $decoratedConnectionClass);
        }

        if (DBALConnection::class !== $decoratedConnectionClass && ! is_subclass_of($decoratedConnectionClass, DBALConnection::class)) {
            throw new \InvalidArgumentException('Expecting FQCN extending ' . DBALConnection::class . ', got ' . $decoratedConnectionClass);
        }

        $this->decoratedConnection = new $decoratedConnectionClass($params, $driver, $config, $eventManager);
    }

    public function getDecoratedConnection(): DBALConnection
    {
        return $this->decoratedConnection;
    }

    public function setGoneAwayDetector(GoneAwayDetector $goneAwayDetector): void
    {
        $this->goneAwayDetector = $goneAwayDetector;
    }

    public function prepare(string $sql): DBALStatement
    {
        // Mysqli executes statement on Statement constructor, so we should retry to reconnect here too
        $attempt = 0;

        do {
            $retry = false;
            try {
                $driverStatement = @$this->decoratedConnection->prepare($sql);

                return new Statement($this, $driverStatement, $sql);
            } catch (\Exception $e) {
                if ($this->canTryAgain($e, $attempt)) {
                    $this->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        } while ($retry);
    }

    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $attempt = 0;

        do {
            $retry = false;
            try {
                return @$this->decoratedConnection->executeQuery($sql, $params, $types, $qcp);
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
                return @$this->decoratedConnection->executeStatement($sql, $params, $types);
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
            return @$this->decoratedConnection->beginTransaction();
        }

        $attempt = 0;

        do {
            $retry = false;
            try {
                return @$this->decoratedConnection->beginTransaction();
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

    public function isConnected()
    {
        return $this->decoratedConnection->isConnected();
    }

    public function connect()
    {
        return $this->decoratedConnection->connect();
    }

    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        return $this->decoratedConnection->fetchFirstColumn($query, $params, $types);
    }

    public function close()
    {
        $this->decoratedConnection->close();
    }
}
