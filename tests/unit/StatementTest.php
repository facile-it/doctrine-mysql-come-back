<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use PDO;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

class StatementTest extends TestCase
{
    use ProphecyTrait;

    public function test_construction(): void
    {
        $sql = 'SELECT 1';
        $connection = $this->prophesize(Connection::class);
        $connection
            ->prepareUnwrapped($sql)
            ->shouldBeCalledTimes(1);

        $statement = new Statement($sql, $connection->reveal());

        $this->assertInstanceOf(Statement::class, $statement);
    }

    public function test_retry(): void
    {
        $sql = 'SELECT :param';
        /** @var DriverStatement|ObjectProphecy $driverStatement1 */
        $driverStatement1 = $this->prophesize(DriverStatement::class);
        /** @var DriverStatement|ObjectProphecy $driverStatement2 */
        $driverStatement2 = $this->prophesize(DriverStatement::class);
        /** @var Connection|ObjectProphecy $connection */
        $connection = $this->prophesize(Connection::class);
        $connection
            ->prepareUnwrapped($sql)
            ->willReturn($driverStatement1->reveal(), $driverStatement2->reveal())
            ->shouldBeCalledTimes(2);

        $statement = new Statement($sql, $connection->reveal());

        $exception = new DBALException('Test');
        $driverStatement1->execute(['param' => 'value'])->willThrow($exception)->shouldBeCalledTimes(1);

        $connection->canTryAgain(0)->willReturn(true)->shouldBeCalledTimes(1);
        $connection->isRetryableException($exception, $sql)->willReturn(true)->shouldBeCalledTimes(1);

        // retry
        $connection->close()->shouldBeCalledTimes(1);
        $driverStatement2->execute(['param' => 'value'])->willReturn(true)->shouldBeCalledTimes(1);

        $this->assertTrue($statement->execute(['param' => 'value']));
    }

    public function test_retry_with_state(): void
    {
        $sql = 'SELECT :value, :param';
        /** @var DriverStatement|ObjectProphecy $driverStatement1 */
        $driverStatement1 = $this->prophesize(DriverStatement::class);
        /** @var DriverStatement|ObjectProphecy $driverStatement2 */
        $driverStatement2 = $this->prophesize(DriverStatement::class);
        /** @var Connection|ObjectProphecy $connection */
        $connection = $this->prophesize(Connection::class);
        $connection
            ->prepareUnwrapped($sql)
            ->willReturn($driverStatement1->reveal(), $driverStatement2)
            ->shouldBeCalledTimes(2);

        $statement = new Statement($sql, $connection->reveal());

        $param = 1;
        $driverStatement1->bindParam('param', $param, PDO::PARAM_INT, null)->willReturn(true)->shouldBeCalledTimes(1);
        $driverStatement1->bindValue('value', 'foo', PDO::PARAM_STR)->willReturn(true)->shouldBeCalledTimes(1);
        $driverStatement1->setFetchMode(PDO::FETCH_COLUMN, 1, null)->willReturn(true)->shouldBeCalledTimes(1);

        $this->assertTrue($statement->bindParam('param', $param, PDO::PARAM_INT));
        $this->assertTrue($statement->bindValue('value', 'foo'));
        $this->assertTrue($statement->setFetchMode(PDO::FETCH_COLUMN, 1));

        $exception = new DBALException('Test');
        $driverStatement1->execute(null)->willThrow($exception)->shouldBeCalledTimes(1);

        $connection->canTryAgain(0)->willReturn(true)->shouldBeCalledTimes(1);
        $connection->isRetryableException($exception, $sql)->willReturn(true)->shouldBeCalledTimes(1);

        // retry
        $connection->close()->shouldBeCalledTimes(1);
        $driverStatement2->bindParam('param', $param, PDO::PARAM_INT, null)->willReturn(true)->shouldBeCalledTimes(1);
        $driverStatement2->bindValue('value', 'foo', PDO::PARAM_STR)->willReturn(true)->shouldBeCalledTimes(1);
        $driverStatement2->setFetchMode(PDO::FETCH_COLUMN, 1, null)->willReturn(true)->shouldBeCalledTimes(1);
        $driverStatement2->execute(null)->willReturn(true)->shouldBeCalledTimes(1);

        $this->assertTrue($statement->execute());
    }

    public function test_retry_fails(): void
    {
        $sql = 'SELECT 1';
        /** @var DriverStatement|ObjectProphecy $driverStatement1 */
        $driverStatement1 = $this->prophesize(DriverStatement::class);
        /** @var DriverStatement|ObjectProphecy $driverStatement2 */
        $driverStatement2 = $this->prophesize(DriverStatement::class);
        /** @var Connection|ObjectProphecy $connection */
        $connection = $this->prophesize(Connection::class);
        $connection
            ->prepareUnwrapped($sql)
            ->willReturn($driverStatement1->reveal(), $driverStatement2->reveal())
            ->shouldBeCalledTimes(2);

        $statement = new Statement($sql, $connection->reveal());

        $exception1 = new DBALException('Test1');
        $driverStatement1->execute(null)->willThrow($exception1)->shouldBeCalledTimes(1);

        $connection->canTryAgain(0)->willReturn(true)->shouldBeCalledTimes(1);
        $connection->isRetryableException($exception1, $sql)->willReturn(true)->shouldBeCalledTimes(1);

        // retry
        $connection->close()->shouldBeCalledTimes(1);
        $exception2 = new DBALException('Test2');
        $driverStatement2->execute(null)->willThrow($exception2)->shouldBeCalledTimes(1);

        $connection->canTryAgain(1)->willReturn(true)->shouldBeCalledTimes(1);
        $connection->isRetryableException($exception2, $sql)->willReturn(false)->shouldBeCalledTimes(1);

        $this->expectException(get_class($exception2));
        $this->expectExceptionMessage($exception2->getMessage());

        $this->assertTrue($statement->execute());
    }

    public function test_state_cache_only_changed_on_success(): void
    {
        $sql = 'SELECT :value, :param';
        /** @var DriverStatement|ObjectProphecy $driverStatement1 */
        $driverStatement1 = $this->prophesize(DriverStatement::class);
        /** @var DriverStatement|ObjectProphecy $driverStatement2 */
        $driverStatement2 = $this->prophesize(DriverStatement::class);
        /** @var Connection|ObjectProphecy $connection */
        $connection = $this->prophesize(Connection::class);
        $connection
            ->prepareUnwrapped($sql)
            ->willReturn($driverStatement1->reveal(), $driverStatement2)
            ->shouldBeCalledTimes(2);

        $statement = new Statement($sql, $connection->reveal());

        $param = 1;
        $driverStatement1->bindParam('param', $param, PDO::PARAM_INT, null)->willReturn(false)->shouldBeCalledTimes(1);
        $driverStatement1->bindValue('value', 'foo', PDO::PARAM_STR)->willReturn(false)->shouldBeCalledTimes(1);
        $driverStatement1->setFetchMode(PDO::FETCH_COLUMN, 1, null)->willReturn(false)->shouldBeCalledTimes(1);

        $this->assertFalse($statement->bindParam('param', $param, PDO::PARAM_INT));
        $this->assertFalse($statement->bindValue('value', 'foo'));
        $this->assertFalse($statement->setFetchMode(PDO::FETCH_COLUMN, 1));

        $exception = new DBALException('Test');
        $driverStatement1->execute(null)->willThrow($exception)->shouldBeCalledTimes(1);

        $connection->canTryAgain(0)->willReturn(true)->shouldBeCalledTimes(1);
        $connection->isRetryableException($exception, $sql)->willReturn(true)->shouldBeCalledTimes(1);

        // retry
        $connection->close()->shouldBeCalledTimes(1);
        $driverStatement2->bindParam(Argument::cetera())->shouldNotBeCalled();
        $driverStatement2->bindValue(Argument::cetera())->shouldNotBeCalled();
        $driverStatement2->setFetchMode(Argument::cetera())->shouldNotBeCalled();
        $driverStatement2->execute(null)->willReturn(true)->shouldBeCalledTimes(1);

        $this->assertTrue($statement->execute());
    }
}
