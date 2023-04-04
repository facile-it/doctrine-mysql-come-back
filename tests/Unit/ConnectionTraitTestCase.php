<?php

namespace Facile\DoctrineMySQLComeBack\Tests\Unit;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\GoneAwayDetector;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\PrimaryReadReplicaConnection;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

abstract class ConnectionTraitTestCase extends TestCase
{
    use ProphecyTrait;

    /**
     * @param Driver $driver
     * @param int $attempts
     *
     * @return Connection|PrimaryReadReplicaConnection
     */
    abstract protected function createConnection(Driver $driver, int $attempts = 0): \Doctrine\DBAL\Connection;

    public function testAttemptCountMustBeRespected(): void
    {
        $driver = $this->prophesize(Driver::class);
        $driverConnection = $this->prophesize(Driver\Connection::class);
        $goneAwayException = new \Exception('MySQL server has gone away');

        $driver->connect(Argument::cetera())
            ->will(function () use ($driver, $goneAwayException, $driverConnection): Driver\Connection {
                // will return successfuly at first attempt, but throw next time to avoid loop
                $driver->connect(Argument::cetera())
                    ->willThrow($goneAwayException);

                return $driverConnection->reveal();
            });
        $driverConnection->beginTransaction()
            ->willThrow($goneAwayException);
        $driverConnection->prepare(Argument::cetera())
            ->willThrow($goneAwayException);

        $connection = $this->createConnection($driver->reveal(), 1);
        try {
            $connection->prepare('SELECT 1');
        } catch (\Throwable $throwable) {
            $this->assertEquals($goneAwayException, $throwable);
        }

        $driver->connect(Argument::cetera())
            ->shouldHaveBeenCalledTimes(2);
    }

    public function testExecuteQueryMustPassSqlToGoneAwayDetector(): void
    {
        $driver = $this->prophesize(Driver::class);
        $connection = $this->createConnection($driver->reveal(), 1);
        $goneAwayDetector = $this->prophesize(GoneAwayDetector::class);
        $connection->setGoneAwayDetector($goneAwayDetector->reveal());

        $sql = 'UPDATE foo SET bar=baz';
        $exception = new \Exception();
        $driver->connect(Argument::cetera())
            ->willThrow($exception);
        $goneAwayDetector->isGoneAwayException(Argument::type(\Throwable::class), $sql)
            ->shouldBeCalledOnce()
            ->willReturn(true);

        try {
            $connection->executeQuery($sql);
        } catch (\Throwable $throwable) {
            $this->assertEquals($exception, $throwable);
        }
    }

    public function testExecuteStatementMustPassSqlToGoneAwayDetector(): void
    {
        $driver = $this->prophesize(Driver::class);
        $connection = $this->createConnection($driver->reveal(), 1);
        $goneAwayDetector = $this->prophesize(GoneAwayDetector::class);
        $connection->setGoneAwayDetector($goneAwayDetector->reveal());

        $sql = 'UPDATE foo SET bar=baz';
        $exception = new \Exception();
        $driver->connect(Argument::cetera())
            ->willThrow($exception);
        $goneAwayDetector->isGoneAwayException(Argument::type(\Throwable::class), $sql)
            ->shouldBeCalledOnce()
            ->willReturn(true);

        try {
            $connection->executeStatement($sql);
        } catch (\Throwable $throwable) {
            $this->assertEquals($exception, $throwable);
        }
    }

    /**
     * @return array{mixed, string}[]
     */
    public function invalidAttemptsDataProvider(): array
    {
        return [
            ['1', 'expecting int, got string'],
            [-1, 'it must not be negative'],
            [1.0, 'expecting int, got double'],
        ];
    }

    protected function mockConfiguration(): Configuration
    {
        $configuration = $this->prophesize(Configuration::class);
        $configuration->getSchemaManagerFactory()
            ->willReturn();
        $configuration->getSQLLogger()
            ->willReturn(null);
        $configuration->getAutoCommit()
            ->willReturn(false);

        return $configuration->reveal();
    }
}
