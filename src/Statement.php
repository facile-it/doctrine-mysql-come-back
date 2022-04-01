<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement as DBALStatement;
use Exception;

/**
 * @internal
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Statement extends DBALStatement
{
    /** @var Connection */
    protected $retriableConnection;

    /** @var DBALStatement */
    private $decoratedStatement;

    /** @var array{int|string, mixed, int|mixed}[] */
    private $boundValues = [];

    /** @var array{int|string, mixed, int, int|null}[] */
    private $boundParams = [];

    public function __construct(Connection $retriableConnection, DBALStatement $statement, string $sql)
    {
        $this->retriableConnection = $retriableConnection;
        $this->decoratedStatement = $statement;
        $this->sql = $sql;
    }

    /**
     * Recreates the statement for retry.
     */
    private function recreateStatement(): void
    {
        $this->decoratedStatement = $this->retriableConnection->prepare($this->sql);

        foreach ($this->boundValues as $boundValue) {
            $this->decoratedStatement->bindValue(...$boundValue);
        }

        foreach ($this->boundParams as $boundParam) {
            $this->decoratedStatement->bindParam(...$boundParam);
        }
    }

    public function execute($params = null): Result
    {
        return $this->executeWithRetry([$this->decoratedStatement, 'execute'], $params);
    }

    public function executeQuery(array $params = []): Result
    {
        return $this->executeWithRetry([$this->decoratedStatement, 'executeQuery'], $params);
    }

    public function executeStatement(array $params = []): int
    {
        return $this->executeWithRetry([$this->decoratedStatement, 'executeStatement'], $params);
    }

    /**
     * @template T
     * @psalm-param callable(...mixed):T $callable
     * @psalm-return T
     */
    private function executeWithRetry($callable, ...$params)
    {
        $decoratedCall = \Closure::fromCallable($callable);

        $attempt = 0;

        do {
            $retry = false;
            try {
                return @$decoratedCall(...$params);
            } catch (Exception $e) {
                if ($this->retriableConnection->canTryAgain($e, $attempt, $this->sql)) {
                    $this->retriableConnection->close();
                    $this->recreateStatement();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        } while ($retry);
    }

    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        if ($this->decoratedStatement->bindValue($param, $value, $type)) {
            $this->boundValues[$param] = [$param, $value, $type];

            return true;
        }

        return false;
    }

    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        if ($this->decoratedStatement->bindParam($param, $variable, $type, $length)) {
            $this->boundParams[$param] = [$param, &$variable, $type, $length];

            return true;
        }

        return false;
    }

    public function getWrappedStatement()
    {
        return $this->decoratedStatement->getWrappedStatement();
    }
}
