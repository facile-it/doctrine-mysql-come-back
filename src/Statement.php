<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Exception;

/**
 * @internal
 */
class Statement extends \Doctrine\DBAL\Statement
{
    /**
     * The connection this statement is bound to and executed on.
     *
     * @var Connection
     */
    protected $conn;

    /**
     * @var mixed[][]
     */
    private $boundValues = [];

    /**
     * @var mixed[][]
     */
    private $boundParams = [];

    /**
     * @var mixed[]|null
     */
    private $fetchMode;

    public function __construct(Connection $conn, StatementInterface $statement, string $sql)
    {
        // Mysqli executes statement on Statement constructor, so we should retry to reconnect here too
        $this->executeWithRetry(__METHOD__, $conn, $statement, $sql);
    }

    /**
     * Recreates the statement for retry.
     */
    private function recreateStatement(): void
    {
        $this->stmt = $this->conn->prepare($this->sql);

        if (null !== $this->fetchMode) {
            call_user_func_array([$this->stmt, 'setFetchMode'], $this->fetchMode);
        }
        foreach ($this->boundValues as $boundValue) {
            call_user_func_array([$this->stmt, 'bindValue'], $boundValue);
        }
        foreach ($this->boundParams as $boundParam) {
            call_user_func_array([$this->stmt, 'bindParam'], $boundParam);
        }
    }

    public function execute($params = null): Result
    {
        return $this->executeWithRetry(__METHOD__, $params);
    }

    public function executeQuery(array $params = []): Result
    {
        return $this->executeWithRetry(__METHOD__, $params);
    }

    public function executeStatement(array $params = []): int
    {
        return $this->executeWithRetry(__METHOD__, $params);
    }

    private function executeWithRetry(string $methodName, ...$params)
    {
        $parentCall = \Closure::fromCallable([$this, $methodName]);
        $parentCall->bindTo($this, parent::class);

        $attempt = 0;

        do {
            $retry = false;
            try {
                return $parentCall(...$params);
            } catch (Exception $e) {
                if ($this->conn->canTryAgain($attempt) && $this->conn->isRetryableException($e, $this->sql)) {
                    $this->conn->close();
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
        if (parent::bindValue($param, $value, $type)) {
            $this->boundValues[$param] = [$param, $value, $type];

            return true;
        }

        return false;
    }

    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        if (parent::bindParam($param, $variable, $type, $length)) {
            $this->boundParams[$param] = [$param, &$variable, $type, $length];

            return true;
        }

        return false;
    }
}
