<?php

use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Statement;

class StatementTest extends \PHPUnit_Framework_TestCase
{

    public function test_construction()
    {
        $sql = 'SELECT 1';
        $connection = $this->prophesize('Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection');
        $connection
            ->prepareUnwrapped($sql)
            ->shouldBeCalledTimes(1);

        $statement = new Statement($sql, $connection->reveal());

        $this->assertInstanceOf('Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Statement', $statement);
    }    
    
}
