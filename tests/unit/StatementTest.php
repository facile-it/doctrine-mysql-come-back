<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class StatementTest extends TestCase
{
    use ProphecyTrait;

    public function testExecuteStatementShouldThrowWhenItsNotRetriable(): void
    {
        $statement = new Statement(
            $this->mockConnection(),
            $this->mockDriverStatement(),
            'SELECT 1'
        );

        $this->expectException(\LogicException::class);

        $statement->executeStatement();
    }

    private function mockConnection(): Connection
    {
        $connection = $this->prophesize(Connection::class);
        $configuration = $this->prophesize(Configuration::class);
        $databasePlatform = $this->prophesize(AbstractPlatform::class);

        $connection->getConfiguration()
            ->willReturn($configuration->reveal());
        $connection->getDatabasePlatform()
            ->willReturn($databasePlatform->reveal());
        $databasePlatform->getName()
            ->shouldNotBeCalled();

        $connection->canTryAgain(Argument::type(\LogicException::class), Argument::cetera())
            ->shouldBeCalledOnce()
            ->willReturn(false);

        return $connection->reveal();
    }

    protected function mockDriverStatement(): DriverStatement
    {
        $statement = $this->prophesize(DriverStatement::class);
        $statement->execute(Argument::cetera())
            ->willThrow(new \LogicException('This should not be retried'));

        return $statement->reveal();
    }
}
