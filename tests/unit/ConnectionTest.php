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
     * @dataProvider publicMethodsDataProvider
     */
    public function testAllParentMethodsAreDecorated(string $methodName): void
    {
        $connection = new \ReflectionClass(Connection::class);
        $method = $connection->getMethod($methodName);

        $this->assertEquals($connection, $method->getDeclaringClass(), 'Method not decorated: ' . $method->getName());
    }

    public function publicMethodsDataProvider(): \Generator
    {
        $dbalClass = new \ReflectionClass(\Doctrine\DBAL\Connection::class);

        foreach ($dbalClass->getMethods() as $method) {
            if (! $method->isPublic()) {
                continue;
            }

            yield [$method->getName()];
        }
    }
}
