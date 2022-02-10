<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\ParameterType;
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
     * @param $sql
     * @param Connection $conn
     */
    public function __construct($sql, ConnectionInterface $conn)
    {
        // Mysqli executes statement on Statement constructor, so we should retry to reconnect here too
        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {
                parent::__construct($sql, $conn);
            } catch (Exception $e) {
                if ($conn->canTryAgain($attempt) && $conn->isRetryableException($e, $sql)) {
                    $conn->close();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Recreate statement for retry.
     */
    private function recreateStatement()
    {
        $this->stmt = $this->conn->getWrappedConnection()->prepare($this->sql);

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

    /**
     * @param array|null $params
     *
     * @throws Exception
     *
     * @return bool
     */
    public function execute($params = null)
    {
        $stmt = null;
        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {
                $stmt = parent::execute($params);
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
        }

        return $stmt;
    }

    /**
     * @param string $param
     * @param mixed  $value
     * @param mixed  $type
     *
     * @return bool
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        if (parent::bindValue($param, $value, $type)) {
            $this->boundValues[$param] = [$param, $value, $type];

            return true;
        }

        return false;
    }

    /**
     * @param string|int   $param
     * @param mixed    $variable
     * @param int      $type
     * @param int|null $length
     *
     * @return bool
     */
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        if (parent::bindParam($param, $variable, $type, $length)) {
            $this->boundParams[$param] = [$param, &$variable, $type, $length];

            return true;
        }

        return false;
    }

    /**
     * @deprecated use one of the fetch- or iterate-related methods
     *
     * @param int   $fetchMode
     * @param mixed $arg2
     * @param mixed $arg3
     *
     * @return bool
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        if (parent::setFetchMode($fetchMode, $arg2, $arg3)) {
            $this->fetchMode = [$fetchMode, $arg2, $arg3];

            return true;
        }

        return false;
    }
}
