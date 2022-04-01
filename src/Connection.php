<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
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

        if (DBALConnection::class !== $decoratedConnectionClass && ! is_subclass_of(
            $decoratedConnectionClass,
            DBALConnection::class
        )) {
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

    public function canTryAgain(
        \Throwable $throwable,
        int $attempt,
        string $sql = null,
        bool $ignoreTransactionLevel = false
    ): bool {
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

    public function connect()
    {
        return $this->decoratedConnection->connect();
    }

    public function close()
    {
        $this->decoratedConnection->close();
    }

    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        return $this->decoratedConnection->fetchFirstColumn($query, $params, $types);
    }

    public function isConnected()
    {
        return $this->decoratedConnection->isConnected();
    }

    public function commit()
    {
        return $this->decoratedConnection->commit();
    }

    public function convertToDatabaseValue($value, $type)
    {
        return $this->decoratedConnection->convertToDatabaseValue($value, $type);
    }

    public function convertToPHPValue($value, $type)
    {
        return $this->decoratedConnection->convertToPHPValue($value, $type);
    }

    public function createExpressionBuilder(): ExpressionBuilder
    {
        return $this->decoratedConnection->createExpressionBuilder();
    }

    public function createQueryBuilder()
    {
        return $this->decoratedConnection->createQueryBuilder();
    }

    public function createSavepoint($savepoint)
    {
        $this->decoratedConnection->createSavepoint($savepoint);
    }

    public function createSchemaManager(): AbstractSchemaManager
    {
        return $this->decoratedConnection->createSchemaManager();
    }

    public function delete($table, array $criteria, array $types = [])
    {
        return $this->decoratedConnection->delete($table, $criteria, $types);
    }

    public function exec(string $sql): int
    {
        return $this->decoratedConnection->exec($sql);
    }

    public function executeCacheQuery($sql, $params, $types, QueryCacheProfile $qcp): Result
    {
        return $this->decoratedConnection->executeCacheQuery($sql, $params, $types, $qcp);
    }

    public function executeUpdate(string $sql, array $params = [], array $types = []): int
    {
        return $this->decoratedConnection->executeUpdate($sql, $params, $types);
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        return $this->decoratedConnection->fetchAllAssociative($query, $params, $types);
    }

    public function fetchAllAssociativeIndexed(string $query, array $params = [], array $types = []): array
    {
        return $this->decoratedConnection->fetchAllAssociativeIndexed($query, $params, $types);
    }

    public function fetchAllKeyValue(string $query, array $params = [], array $types = []): array
    {
        return $this->decoratedConnection->fetchAllKeyValue($query, $params, $types);
    }

    public function fetchAllNumeric(string $query, array $params = [], array $types = []): array
    {
        return $this->decoratedConnection->fetchAllNumeric($query, $params, $types);
    }

    public function fetchAssociative(string $query, array $params = [], array $types = [])
    {
        return $this->decoratedConnection->fetchAssociative($query, $params, $types);
    }

    public function fetchNumeric(string $query, array $params = [], array $types = [])
    {
        return $this->decoratedConnection->fetchNumeric($query, $params, $types);
    }

    public function fetchOne(string $query, array $params = [], array $types = [])
    {
        return $this->decoratedConnection->fetchOne($query, $params, $types);
    }

    public function getConfiguration()
    {
        return $this->decoratedConnection->getConfiguration();
    }

    public function getDatabase()
    {
        return $this->decoratedConnection->getDatabase();
    }

    public function getDatabasePlatform()
    {
        return $this->decoratedConnection->getDatabasePlatform();
    }

    public function getDriver()
    {
        return $this->decoratedConnection->getDriver();
    }

    public function getEventManager()
    {
        return $this->decoratedConnection->getEventManager();
    }

    public function getExpressionBuilder()
    {
        return $this->decoratedConnection->getExpressionBuilder();
    }

    public function getNativeConnection()
    {
        return $this->decoratedConnection->getNativeConnection();
    }

    public function getNestTransactionsWithSavepoints()
    {
        return $this->decoratedConnection->getNestTransactionsWithSavepoints();
    }

    public function getParams()
    {
        return $this->decoratedConnection->getParams();
    }

    public function getSchemaManager()
    {
        return $this->decoratedConnection->getSchemaManager();
    }

    public function getTransactionIsolation()
    {
        return $this->decoratedConnection->getTransactionIsolation();
    }

    public function getTransactionNestingLevel()
    {
        return $this->decoratedConnection->getTransactionNestingLevel();
    }

    public function getWrappedConnection()
    {
        return $this->decoratedConnection->getWrappedConnection();
    }

    public function insert($table, array $data, array $types = [])
    {
        return $this->decoratedConnection->insert($table, $data, $types);
    }

    public function isAutoCommit()
    {
        return $this->decoratedConnection->isAutoCommit();
    }

    public function isRollbackOnly()
    {
        return $this->decoratedConnection->isRollbackOnly();
    }

    public function isTransactionActive()
    {
        return $this->decoratedConnection->isTransactionActive();
    }

    public function iterateAssociative(string $query, array $params = [], array $types = []): \Traversable
    {
        return $this->decoratedConnection->iterateAssociative($query, $params, $types);
    }

    public function iterateAssociativeIndexed(string $query, array $params = [], array $types = []): \Traversable
    {
        return $this->decoratedConnection->iterateAssociativeIndexed($query, $params, $types);
    }

    public function iterateColumn(string $query, array $params = [], array $types = []): \Traversable
    {
        return $this->decoratedConnection->iterateColumn($query, $params, $types);
    }

    public function iterateKeyValue(string $query, array $params = [], array $types = []): \Traversable
    {
        return $this->decoratedConnection->iterateKeyValue($query, $params, $types);
    }

    public function iterateNumeric(string $query, array $params = [], array $types = []): \Traversable
    {
        return $this->decoratedConnection->iterateNumeric($query, $params, $types);
    }

    public function lastInsertId($name = null)
    {
        return $this->decoratedConnection->lastInsertId($name);
    }

    public function query(string $sql): Result
    {
        return $this->decoratedConnection->query($sql);
    }

    public function quote($value, $type = ParameterType::STRING)
    {
        return $this->decoratedConnection->quote($value, $type);
    }

    public function quoteIdentifier($str)
    {
        return $this->decoratedConnection->quoteIdentifier($str);
    }

    public function releaseSavepoint($savepoint)
    {
        return $this->decoratedConnection->releaseSavepoint($savepoint);
    }

    public function rollBack()
    {
        return $this->decoratedConnection->rollBack();
    }

    public function rollbackSavepoint($savepoint)
    {
        $this->decoratedConnection->rollbackSavepoint($savepoint);
    }

    public function setAutoCommit($autoCommit)
    {
        return $this->decoratedConnection->setAutoCommit($autoCommit);
    }

    public function setNestTransactionsWithSavepoints($nestTransactionsWithSavepoints)
    {
        $this->decoratedConnection->setNestTransactionsWithSavepoints($nestTransactionsWithSavepoints);
    }

    public function setRollbackOnly()
    {
        $this->decoratedConnection->setRollbackOnly();
    }

    public function setTransactionIsolation($level)
    {
        return $this->decoratedConnection->setTransactionIsolation($level);
    }

    public function transactional(\Closure $func)
    {
        return $this->decoratedConnection->transactional($func);
    }

    public function update($table, array $data, array $criteria, array $types = [])
    {
        return $this->decoratedConnection->update($table, $data, $criteria, $types);
    }
}
