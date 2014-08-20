<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\DBALException;
use PDO,
    Doctrine\DBAL\Types\Type,
    Doctrine\DBAL\Driver\Statement as DriverStatement;

/**
 * Class Statement
 * @package Facile\DoctrineMySQLComeBack\Doctrine\DBAL
 */
class Statement implements \IteratorAggregate, DriverStatement
{
    /**
     * @var
     */
    protected $sql;
    /**
     * @var array
     */
    protected $params = array();
    /**
     * @var array
     */
    protected $types = array();
    /**
     * @var
     */
    protected $stmt;
    /**
     * @var
     */
    protected $platform;
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
        $this->platform = $conn->getDatabasePlatform();
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
        $logger = $this->conn->getConfiguration()->getSQLLogger();
        if ($logger) {
            $logger->startQuery($this->sql, $this->params, $this->types);
        }

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
                    throw DBALException::driverExceptionDuringQuery(
                        $e,
                        $this->sql,
                        $this->conn->resolveParams($this->params, $this->types)
                    );
                }
            }
        }

        if ($logger) {
            $logger->stopQuery();
        }
        $this->params = array();
        $this->types = array();

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
        $this->params[$name] = $value;
        $this->types[$name] = $type;
        if ($type !== null) {
            if (is_string($type)) {
                $type = Type::getType($type);
            }
            if ($type instanceof Type) {
                $value = $type->convertToDatabaseValue($value, $this->platform);
                $bindingType = $type->getBindingType();
            } else {
                $bindingType = $type; // PDO::PARAM_* constants
            }
            return $this->stmt->bindValue($name, $value, $bindingType);
        } else {
            return $this->stmt->bindValue($name, $value);
        }
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
        if ($arg2 === null) {
            return $this->stmt->setFetchMode($fetchMode);
        } else {
            if ($arg3 === null) {
                return $this->stmt->setFetchMode($fetchMode, $arg2);
            }
        }

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
        if ($fetchArgument !== 0) {
            return $this->stmt->fetchAll($fetchMode, $fetchArgument);
        }
        return $this->stmt->fetchAll($fetchMode);
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
     * @return mixed
     */
    public function getWrappedStatement()
    {
        return $this->stmt;
    }
}