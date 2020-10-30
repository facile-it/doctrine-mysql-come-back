<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Driver;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Driver\ServerGoneAwayExceptionsAwareInterface;
use Doctrine\DBAL\Configuration;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ConnectionTest extends TestCase
{
    use ProphecyTrait;

    /** @var Connection */
    protected $connection;

    public function setUp(): void
    {
        $driver = $this->prophesize(Driver::class)
            ->willImplement(ServerGoneAwayExceptionsAwareInterface::class);
        $configuration = $this->prophesize(Configuration::class);
        $eventManager = $this->prophesize(EventManager::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $params = [
            'driverOptions' => [
                'x_reconnect_attempts' => 3
            ],
            'platform' => $platform->reveal()
        ];

        $this->connection = new Connection(
            $params,
            $driver->reveal(),
            $configuration->reveal(),
            $eventManager->reveal()
        );
    }

    public function testConstructor(): void
    {
        $driver = $this->prophesize(Driver::class)
            ->willImplement(ServerGoneAwayExceptionsAwareInterface::class);
        $configuration = $this->prophesize(Configuration::class);
        $eventManager = $this->prophesize(EventManager::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $params = [
            'driverOptions' => [
                'x_reconnect_attempts' => 999
            ],
            'platform' => $platform->reveal()
        ];

        $connection = new Connection(
            $params,
            $driver->reveal(),
            $configuration->reveal(),
            $eventManager->reveal()
        );

        static::assertInstanceOf(Connection::class, $connection);
    }

    public function testConstructorWithInvalidDriver(): void
    {
        $driver = $this->prophesize(Driver::class);
        $configuration = $this->prophesize(Configuration::class);
        $eventManager = $this->prophesize(EventManager::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $params = [
            'driverOptions' => [
                'x_reconnect_attempts' => 999
            ],
            'platform' => $platform->reveal()
        ];

        $this->expectException(InvalidArgumentException::class);

        new Connection(
            $params,
            $driver->reveal(),
            $configuration->reveal(),
            $eventManager->reveal()
        );
    }

    /**
     * @dataProvider isUpdateQueryDataProvider
     *
     * @param string $query
     * @param boolean $expected
     */
    public function testIsUpdateQuery(string $query, bool $expected): void
    {
        static::assertEquals($expected, $this->connection->isUpdateQuery($query));
    }

    public function isUpdateQueryDataProvider(): array
    {
        return [
            ['UPDATE ', true],
            ['DELETE ', true],
            ['DELETE ', true],
            ['SELECT ', false],
            ['select ', false],
            ["\n\tSELECT\n", false],
            ['(select ', false],
            [' (select ', false],
            [' 
            (select ', false],
            [' UPDATE WHERE (SELECT ', true],
            [' UPDATE WHERE 
            (select ', true],
        ];
    }
}
