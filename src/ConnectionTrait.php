<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement as DBALStatement;
use Doctrine\DBAL\Types\Type;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\GoneAwayDetector;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\MySQLGoneAwayDetector;

trait ConnectionTrait
{
    protected GoneAwayDetector $goneAwayDetector;

    protected int $reconnectAttempts = 0;

    private ?\ReflectionProperty $selfReflectionNestingLevelProperty = null;

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

        /** @psalm-suppress InternalMethod
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        parent::__construct($params, $driver, $config, $eventManager);
    }

    public function setGoneAwayDetector(GoneAwayDetector $goneAwayDetector): void
    {
        $this->goneAwayDetector = $goneAwayDetector;
    }

    abstract public function connect();

    public function prepare(string $sql): DBALStatement
    {
        // Mysqli executes statement on Statement constructor, so we should retry to reconnect here too
        $attempt = 0;

        do {
            try {
                /** @psalm-suppress InternalMethod */
                $this->connect();

                /**
                 * @psalm-suppress InternalMethod
                 * @psalm-suppress PossiblyNullReference
                 */
                $driverStatement = @$this->_conn->prepare($sql);

                return new Statement($this, $driverStatement, $sql);
            } catch (\Exception $e) {
                if ($this->canTryAgain($e, $attempt)) {
                    $this->close();
                    ++$attempt;
                } else {
                    throw $e;
                }
            }
        } while (true);
    }

    /**
     * @param string $sql
     * @param list<mixed>|array<string, mixed>                                     $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     */
    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $attempt = 0;

        do {
            try {
                return @parent::executeQuery($sql, $params, $types, $qcp);
            } catch (DBALException $e) {
                if ($this->canTryAgain($e, $attempt, $sql)) {
                    $this->close();
                    ++$attempt;
                } else {
                    throw $e;
                }
            }
        } while (true);
    }

    /**
     * @param string $sql
     * @param list<mixed>|array<string, mixed>                                     $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function executeStatement($sql, array $params = [], array $types = [])
    {
        $attempt = 0;

        do {
            try {
                return @parent::executeStatement($sql, $params, $types);
            } catch (DBALException $e) {
                if ($this->canTryAgain($e, $attempt, $sql)) {
                    $this->close();
                    ++$attempt;
                } else {
                    throw $e;
                }
            }
        } while (true);
    }

    public function beginTransaction()
    {
        if (0 !== $this->getTransactionNestingLevel()) {
            return @parent::beginTransaction();
        }

        $attempt = 0;

        do {
            try {
                return parent::beginTransaction();
            } catch (\Throwable $e) {
                if ($this->canTryAgain($e, $attempt, '', true)) {
                    $this->close();
                    if (0 < $this->getTransactionNestingLevel()) {
                        $this->resetTransactionNestingLevel();
                    }
                    ++$attempt;
                } else {
                    throw $e;
                }
            }
        } while (true);
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