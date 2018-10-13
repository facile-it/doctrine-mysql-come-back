<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Statement;
use Prophecy\Argument;

class StatementTest extends \PHPUnit\Framework\TestCase
{
    public function test_construction()
    {
        $sql = 'SELECT 1';
        $connection = $this->prophesize(Connection::class);
        $connection
            ->prepareUnwrapped(
                Argument::exact($sql)
            )
            ->shouldBeCalledTimes(1);

        $statement = new Statement($sql, $connection->reveal());

        $this->assertInstanceOf(Statement::class, $statement);
    }
}
