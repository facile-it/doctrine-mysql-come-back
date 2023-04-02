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
     * @return array{mixed}[]
     */
    public function invalidAttemptsDataProvider(): array
    {
        return [
            ['1'],
            [-1],
            [1.0],
        ];
    }

    protected function mockConfiguration(): Configuration
    {
        $configuration = $this->prophesize(Configuration::class);
        $configuration->getSchemaManagerFactory()
            ->willReturn();
        $configuration->getAutoCommit()
            ->willReturn(false);

        return $configuration->reveal();
    }
}
