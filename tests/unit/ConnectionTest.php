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

    public function testAllParentMethodsAreDecorated(): void
    {
        $connection = new \ReflectionClass(Connection::class);
        $missingMethods = [];

        foreach ($this->getPublicNonFinalMethods() as $methodName) {
            $method = $connection->getMethod($methodName);

            if (Connection::class !== $method->getDeclaringClass()->getName()) {
                $missingMethods[] = $methodName;
            }
        }

        $this->assertEmpty($missingMethods, 'Some methods are not decorated: ' . PHP_EOL . implode(PHP_EOL, $missingMethods));
    }

    /**
     * @return list<string>
     */
    public function getPublicNonFinalMethods(): array
    {
        $dbalClass = new \ReflectionClass(\Doctrine\DBAL\Connection::class);

        $methods = [];

        foreach ($dbalClass->getMethods() as $method) {
            if (! $method->isPublic()) {
                continue;
            }

            if ($method->isFinal()) {
                continue;
            }

            $methods[] = $method->getName();
        }

        sort($methods);

        return $methods;
    }
}
