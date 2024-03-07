<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement as DBALStatement;
use Doctrine\DBAL\Types\Type;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\GoneAwayDetector;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\MySQLGoneAwayDetector;

/**
 * @psalm-require-extends \Doctrine\DBAL\Connection
 *
 * @psalm-type WrapperParameterType = string|Type|ParameterType|ArrayParameterType
 * @psalm-type WrapperParameterTypeArray = array<int<0, max>, WrapperParameterType>|array<string, WrapperParameterType>
 */
trait ConnectionTrait
{
    protected GoneAwayDetector $goneAwayDetector;

    protected int $maxReconnectAttempts = 0;

    protected int $currentAttempts = 0;

    private bool $hasBeenClosedWithAnOpenTransaction = false;

    private ?\ReflectionProperty $selfReflectionNestingLevelProperty = null;

    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null
    ) {
        if (isset($params['driverOptions']['x_reconnect_attempts'])) {
            $this->maxReconnectAttempts = $this->validateAttemptsOption($params['driverOptions']['x_reconnect_attempts']);
            unset($params['driverOptions']['x_reconnect_attempts']);
        }

        $this->goneAwayDetector = new MySQLGoneAwayDetector();

        /**
         * @psalm-suppress InternalMethod
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        parent::__construct($params, $driver, $config);
    }

    /**
     * @param mixed $attempts
     */
    private function validateAttemptsOption($attempts): int
    {
        if (! is_int($attempts)) {
            throw new \InvalidArgumentException('Invalid x_reconnect_attempts option: expecting int, got ' . gettype($attempts));
        }

        if ($attempts < 0) {
            throw new \InvalidArgumentException('Invalid x_reconnect_attempts option: it must not be negative');
        }

        return $attempts;
    }

    public function setGoneAwayDetector(GoneAwayDetector $goneAwayDetector): void
    {
        $this->goneAwayDetector = $goneAwayDetector;
    }

    /**
     * @template R
     *
     * @param callable():R $callable
     *
     * @return R
     */
    private function doWithRetry(callable $callable, string $sql = null)
    {
        try {
            attempt:
            $result = $callable();
        } catch (\Exception $e) {
            if (! $this->canTryAgain($e, $sql)) {
                throw $e;
            }

            $this->close();
            $this->increaseAttemptCount();

            goto attempt;
        }

        $this->resetAttemptCount();

        /** @psalm-suppress PossiblyUndefinedVariable */
        return $result;
    }

    /**
     * @internal
     */
    public function increaseAttemptCount(): void
    {
        ++$this->currentAttempts;
    }

    /**
     * @internal
     */
    public function resetAttemptCount(): void
    {
        $this->currentAttempts = 0;
    }

    /**
     * @param string $connectionName
     */
    public function connect(?string $connectionName = null): DriverConnection
    {
        $this->hasBeenClosedWithAnOpenTransaction = false;

        /** @psalm-suppress InternalMethod */
        return parent::connect($connectionName);
    }

    public function close(): void
    {
        if ($this->getTransactionNestingLevel() > 0) {
            $this->hasBeenClosedWithAnOpenTransaction = true;
        }

        parent::close();
    }

    public function prepare(string $sql): DBALStatement
    {
        return $this->doWithRetry(function () use ($sql): Statement {
            $dbalStatement = parent::prepare($sql);

            return Statement::fromDBALStatement($this, $dbalStatement);
        });
    }

    /**
     * @param string $sql
     * @param list<mixed>|array<string, mixed>                                     $params
     *
     * @psalm-param WrapperParameterTypeArray $types
     */
    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        return $this->doWithRetry(function () use ($sql, $params, $types, $qcp): Result {
            return @parent::executeQuery($sql, $params, $types, $qcp);
        }, $sql);
    }

    /**
     * @param string $sql
     * @param list<mixed>|array<string, mixed> $params
     *
     * @psalm-param WrapperParameterTypeArray $types
     *
     * @return int|numeric-string
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        return $this->doWithRetry(function () use ($sql, $params, $types) {
            return @parent::executeStatement($sql, $params, $types);
        }, $sql);
    }

    public function beginTransaction(): void
    {
        if ($this->hasBeenClosedWithAnOpenTransaction || 0 !== $this->getTransactionNestingLevel()) {
            @parent::beginTransaction();

            return;
        }

        $this->doWithRetry(function (): bool {
            parent::beginTransaction();

            return true;
        });
    }

    public function canTryAgain(\Throwable $throwable, string $sql = null): bool
    {
        if ($this->hasBeenClosedWithAnOpenTransaction) {
            return false;
        }

        if ($this->currentAttempts >= $this->maxReconnectAttempts) {
            return false;
        }

        return $this->goneAwayDetector->isGoneAwayException($throwable, $sql);
    }
}
