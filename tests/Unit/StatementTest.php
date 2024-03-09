<?php

namespace Facile\DoctrineMySQLComeBack\Tests\Unit;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Statement;
use Facile\DoctrineMySQLComeBack\Tests\DeprecationTrait;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class StatementTest extends BaseUnitTestCase
{
    use ProphecyTrait;
    use DeprecationTrait;

    public function testExecuteStatementShouldThrowWhenItsNotRetriable(): void
    {
        $connection = $this->mockConnection();
        $statement = Statement::fromDBALStatement(
            $connection,
            $this->createDriverStatement($connection),
        );

        $this->expectException(\LogicException::class);

        $statement->executeStatement();
    }

    /**
     * @dataProvider attemptsDataProvider
     */
    public function testReconnectionAttempsShouldRunOut(int $attempts): void
    {
        $driver = $this->prophesize(Driver::class);
        $goneAwayException = new \Exception('MySQL server has gone away');
        $driver->connect(Argument::cetera())
            ->willThrow($goneAwayException);

        $connection = new \Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection(
            [
                'driverOptions' => [
                    'x_reconnect_attempts' => $attempts,
                ],
            ],
            $driver->reveal(),
            $this->mockConfiguration(),
        );

        try {
            $connection->prepare('SELECT 1');
        } catch (\Throwable $throwable) {
            $this->assertEquals($goneAwayException, $throwable, 'Got unexpected exception');
        }

        $driver->connect(Argument::cetera())
            ->shouldHaveBeenCalledTimes($attempts + 1);
    }

    /**
     * @return array{int}[]
     */
    public function attemptsDataProvider(): array
    {
        return [
            [0],
            [1],
            [5],
        ];
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

        $connection->canTryAgain(Argument::type(\LogicException::class), 'SELECT 1')
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

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    protected function createDriverStatement(Connection $connection): \Doctrine\DBAL\Statement
    {
        /** @psalm-suppress InternalMethod */
        return new \Doctrine\DBAL\Statement(
            $connection,
            $this->mockDriverStatement(),
            'SELECT 1'
        );
    }
}
