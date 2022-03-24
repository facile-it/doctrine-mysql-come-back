<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Connection as DBALConnection;
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
        $attempt = 0;

        do {
            $retry = false;
            try {
                return @parent::execute($params);
            } catch (\Doctrine\DBAL\Exception $e) {
                if ($this->conn->canTryAgain($e, $attempt)) {
                    $this->conn->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        } while ($retry);
    }

    public function executeQuery(array $params = []): Result
    {
        $attempt = 0;

        do {
            $retry = false;
            try {
                return @parent::executeQuery($params);
            } catch (\Doctrine\DBAL\Exception $e) {
                if ($this->conn->canTryAgain($e, $attempt)) {
                    $this->conn->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        } while ($retry);
    }

    public function executeStatement(array $params = []): int
    {
        $attempt = 0;

        do {
            $retry = false;
            try {
                return @parent::executeStatement($params);
            } catch (\Doctrine\DBAL\Exception $e) {
                if ($this->conn->canTryAgain($e, $attempt)) {
                    $this->conn->close();
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
