<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use PDO;
use Doctrine\DBAL\Driver\Statement as DriverStatement;

/**
 * Class Statement.
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
     * @param string $sql
     * @param Connection $conn
     */
    public function __construct($sql, Connection $conn)
    {
        $this->sql = $sql;
        $this->conn = $conn;
        $this->createStatement();
    }

    /**
     * Create Statement.
     */
    private function createStatement()
    {
        $this->stmt = $this->conn->prepareUnwrapped($this->sql);
    }

    /**
     * @param array|null $params
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function execute($params = null)
    {
        $stmt = false;
        $attempt = 0;
        $retry = true;
        while ($retry) {
            $retry = false;
            try {
                $stmt = $this->stmt->execute($params);
            } catch (\Exception $e) {
                if ($this->conn->canTryAgain($attempt) && $this->conn->isRetryableException($e, $this->sql)) {
                    $this->conn->close();
                    $this->createStatement();
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
     * @param string $name
     * @param mixed  $value
     * @param mixed  $type
     *
     * @return bool
     */
    public function bindValue($name, $value, $type = null)
    {
        return $this->stmt->bindValue($name, $value, $type);
    }

    /**
     * @param string   $name
     * @param mixed    $var
     * @param int      $type
     * @param int|null $length
     *
     * @return bool
     */
    public function bindParam($name, &$var, $type = PDO::PARAM_STR, $length = null)
    {
        return $this->stmt->bindParam($name, $var, $type, $length);
    }

    /**
     * @return bool
     */
    public function closeCursor()
    {
        return $this->stmt->closeCursor();
    }

    /**
     * @return int
     */
    public function columnCount()
    {
        return $this->stmt->columnCount();
    }

    /**
     * @return string|int|bool
     */
    public function errorCode()
    {
        return $this->stmt->errorCode();
    }

    /**
     * @return array
     */
    public function errorInfo()
    {
        return $this->stmt->errorInfo();
    }

    /**
     * @param int   $fetchMode
     * @param mixed $arg2
     * @param mixed $arg3
     *
     * @return bool
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        return $this->stmt->setFetchMode($fetchMode, $arg2, $arg3);
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        return $this->stmt;
    }

    /**
     * @param int|null $fetchMode
     * @param int $cursorOrientation Only for doctrine/DBAL >= 2.6
     * @param int $cursorOffset Only for doctrine/DBAL >= 2.6
     * @return mixed
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        return $this->stmt->fetch($fetchMode, $cursorOrientation, $cursorOffset);
    }

    /**
     * @param int|null $fetchMode
     * @param int $fetchArgument Only for doctrine/DBAL >= 2.6
     * @param null $ctorArgs Only for doctrine/DBAL >= 2.6
     * @return mixed
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        return $this->stmt->fetchAll($fetchMode, $fetchArgument, $ctorArgs);
    }

    /**
     * @param int $columnIndex
     *
     * @return mixed
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->stmt->fetchColumn($columnIndex);
    }

    /**
     * @return int
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
