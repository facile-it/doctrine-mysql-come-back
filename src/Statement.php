<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Exception;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;

/**
 * @internal
 */
class Statement extends \Doctrine\DBAL\Statement
{
    /** @var Connection|PrimaryReadReplicaConnection */
    protected \Doctrine\DBAL\Connection $retriableConnection;

    private array $boundValues = [];

    /**
     * @param Connection|PrimaryReadReplicaConnection $retriableConnection
     */
    public static function fromDBALStatement(\Doctrine\DBAL\Connection $retriableConnection, \Doctrine\DBAL\Statement $statement): self
    {
        return new self($retriableConnection, $statement->stmt, $statement->sql);
    }

    /**
     * @param Connection|PrimaryReadReplicaConnection $retriableConnection
     */
    private function __construct(\Doctrine\DBAL\Connection $retriableConnection, Driver\Statement $statement, string $sql)
    {
        /** @psalm-suppress InternalMethod */
        parent::__construct($retriableConnection, $statement, $sql);

        $this->retriableConnection = $retriableConnection;
    }

    /**
     * Recreates the statement for retry.
     */
    private function recreateStatement(): void
    {
        $ref = new \ReflectionMethod($this->conn, 'connect');

        /** @var DriverConnection $wrappedConnection */
        $wrappedConnection = $ref->invoke($this->conn);

        $this->stmt = $wrappedConnection->prepare($this->sql);

        /** @var mixed $value */
        foreach ($this->boundValues as $param => $value) {
            parent::bindValue($param, $value, $this->types[$param] ?? ParameterType::STRING);
        }
    }

    public function bindValue($param, $value, $type = ParameterType::STRING): void
    {
        $this->boundValues[$param] = $value;
        parent::bindValue($param, $value, $type);
    }

    public function executeQuery(): Result
    {
        return $this->executeWithRetry(fn () => parent::executeQuery());
    }

    public function executeStatement(): int|string
    {
        return $this->executeWithRetry(fn () => parent::executeStatement());
    }

    /**
     * @template P
     * @template R
     *
     * @param callable(P):R $callable
     * @param P ...$params
     *
     * @return R
     */
    private function executeWithRetry(callable $callable, ...$params)
    {
        $parentCall = \Closure::fromCallable($callable);
        $parentCall->bindTo($this, parent::class);

        try {
            attempt:
            $result = $parentCall(...$params);
        } catch (Exception $e) {
            if (! $this->retriableConnection->canTryAgain($e, $this->sql)) {
                throw $e;
            }

            $this->retriableConnection->increaseAttemptCount();
            $this->recreateStatement();

            goto attempt;
        }

        $this->retriableConnection->resetAttemptCount();

        /** @psalm-suppress PossiblyUndefinedVariable */
        return $result;
    }
}
