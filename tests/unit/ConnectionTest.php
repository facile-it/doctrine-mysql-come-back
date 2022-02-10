<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ConnectionTest extends TestCase
{
    use ProphecyTrait;

    public function testConstructor(): void
    {
        $driver = $this->prophesize(Driver::class);
        $configuration = $this->prophesize(Configuration::class);
        $configuration->getAutoCommit()
            ->willReturn(false);
        $eventManager = $this->prophesize(EventManager::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $params = [
            'driverOptions' => [
                'x_reconnect_attempts' => 999,
            ],
            'platform' => $platform->reveal(),
        ];

        $connection = new Connection(
            $params,
            $driver->reveal(),
            $configuration->reveal(),
            $eventManager->reveal()
        );

        static::assertInstanceOf(Connection::class, $connection);
    }

    /**
     * @dataProvider isUpdateQueryDataProvider
     *
     * @param string $query
     * @param bool $expected
     */
    public function testIsUpdateQuery(string $query, bool $expected): void
    {
        $driver = $this->prophesize(Driver::class);
        $configuration = $this->prophesize(Configuration::class);
        $configuration->getAutoCommit()
            ->willReturn(false);
        $eventManager = $this->prophesize(EventManager::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $params = [
            'driverOptions' => [
                'x_reconnect_attempts' => 3,
            ],
            'platform' => $platform->reveal(),
        ];

        $connection = new Connection(
            $params,
            $driver->reveal(),
            $configuration->reveal(),
            $eventManager->reveal()
        );

        static::assertEquals($expected, $connection->isUpdateQuery($query));
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
