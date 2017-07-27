<?php

use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Statement;

class StatementTest extends \PHPUnit\Framework\TestCase
{
    public function test_construction()
    {
        $sql = 'SELECT 1';
        $connection = $this->prophesize(Connection::class);
        $connection
            ->prepareUnwrapped($sql)
            ->shouldBeCalledTimes(1);

        $statement = new Statement($sql, $connection->reveal());

        $this->assertInstanceOf(Statement::class, $statement);
    }
}
