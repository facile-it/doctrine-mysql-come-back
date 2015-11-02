<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use PDO,
    Doctrine\DBAL\Driver\Statement as DriverStatement;

/**
 * Class Statement
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL
 */
class Statement implements \IteratorAggregate, DriverStatement
{
    /**
     * @var string
     */
    protected $sql;
    /**
     * @var \Doctrine\DBAL\Statement
     */
    protected $stmt;
    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @param $sql
     * @param Connection $conn
     */
    public function __construct($sql, Connection $conn)
    {
        $this->sql = $sql;
        $this->conn = $conn;
        $this->createStatement();
    }

    /**
     *
     */
    private function createStatement()
    {
        $this->stmt = $this->conn->prepareUnwrapped($this->sql);
    }

    /**
     * @param null $params
     * @return null
     * @throws
     */
    public function execute($params = null)
    {
        $stmt = null;
        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {
                $stmt = $this->stmt->execute($params);
            } catch (\Exception $e) {
                if ($this->conn->validateReconnectAttempt($e, $attempt)) {
                    $this->conn->close();
                    $this->createStatement();
                    $attempt++;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        }

        return $stmt;
    }

    /**
     * @param $name
     * @param $value
     * @param null $type
     * @return mixed
     */
    public function bindValue($name, $value, $type = null)
    {
        return $this->stmt->bindValue($name, $value, $type);
    }

    /**
     * @param $name
     * @param $var
     * @param int $type
     * @param null $length
     * @return mixed
     */
    public function bindParam($name, &$var, $type = PDO::PARAM_STR, $length = null)
    {
        return $this->stmt->bindParam($name, $var, $type, $length);
    }

    /**
     * @return mixed
     */
    function closeCursor()
    {
        return $this->stmt->closeCursor();
    }

    /**
     * @return mixed
     */
    function columnCount()
    {
        return $this->stmt->columnCount();
    }

    /**
     * @return mixed
     */
    function errorCode()
    {
        return $this->stmt->errorCode();
    }

    /**
     * @return mixed
     */
    function errorInfo()
    {
        return $this->stmt->errorInfo();
    }

    /**
     * @param $fetchMode
     * @param null $arg2
     * @param null $arg3
     * @return mixed
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        return $this->stmt->setFetchMode($fetchMode, $arg2, $arg3);
    }

    /**
     * @return mixed
     */
    public function getIterator()
    {
        return $this->stmt;
    }

    /**
     * @param null $fetchMode
     * @return mixed
     */
    public function fetch($fetchMode = null)
    {
        return $this->stmt->fetch($fetchMode);
    }

    /**
     * @param null $fetchMode
     * @param int $fetchArgument
     * @return mixed
     */
    public function fetchAll($fetchMode = null, $fetchArgument = 0)
    {
        return $this->stmt->fetchAll($fetchMode, $fetchArgument);
    }

    /**
     * @param int $columnIndex
     * @return mixed
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->stmt->fetchColumn($columnIndex);
    }

    /**
     * @return mixed
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    /**
     * @return \Doctrine\DBAL\Statement
     */
    public function getWrappedStatement()
    {
        return $this->stmt;
    }
}
