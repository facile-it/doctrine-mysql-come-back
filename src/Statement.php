<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use Exception;

/**
 * @internal
 */
class Statement extends \Doctrine\DBAL\Statement
{
    protected Connection $retriableConnection;

    /** @var mixed[] */
    private array $boundValues = [];

    public static function fromDBALStatement(Connection $retriableConnection, \Doctrine\DBAL\Statement $statement): self
    {
        return new self($retriableConnection, $statement->stmt, $statement->sql);
    }

    private function __construct(Connection $retriableConnection, Driver\Statement $statement, string $sql)
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
            $type = ParameterType::STRING;
            if (isset($this->types[$param])) {
                $type = $this->types[$param];
            }

            parent::bindValue($param, $value, $type);
        }
    }

    public function bindValue(string|int $param, mixed $value, string|ParameterType|Type $type = ParameterType::STRING): void
    {
        $this->boundValues[$param] = $value;
        parent::bindValue($param, $value, $type);
    }

    public function executeQuery(): Result
    {
        return $this->executeWithRetry(parent::executeQuery(...));
    }

    public function executeStatement(): int|string
    {
        return $this->executeWithRetry(parent::executeStatement(...));
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
        try {
            attempt:
            $result = $callable(...$params);
        } catch (Exception $e) {
            if (! $this->retriableConnection->canTryAgain($e, $this->sql)) {
                throw $e;
            }

            $this->retriableConnection->increaseAttemptCount();
            $this->recreateStatement();

            goto attempt;
        }

        /** @psalm-suppress PossiblyUndefinedVariable */
        return $result;
    }
}
