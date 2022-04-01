<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement as DBALStatement;
use Exception;

/**
 * @internal
 */
class Statement extends DBALStatement
{
    /** @var Connection */
    protected $retriableConnection;

    /** @var DBALStatement */
    private $decoratedStatement;

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
        // TODO -- rebind parameters?
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

    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->decoratedStatement->bindValue($param, $value, $type);
    }

    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        return $this->decoratedStatement->bindParam($param, $variable, $type, $length);
    }

    public function getWrappedStatement()
    {
        return $this->decoratedStatement->getWrappedStatement();
    }
}
