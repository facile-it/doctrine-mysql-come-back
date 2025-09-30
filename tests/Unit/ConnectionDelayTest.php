<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Tests\Unit;

use Doctrine\DBAL\Driver;
use Facile\DoctrineMySQLComeBack\Tests\Functional\Spy\Connection;
use PHPUnit\Framework\Attributes\DataProvider;

class ConnectionDelayTest extends BaseUnitTestCase
{
    public function testDelayOptionsAreApplied(): void
    {
        $driver = $this->prophesize(Driver::class);
        $connection = new Connection(
            [
                'driverOptions' => [
                    'x_reconnect_attempts' => 3,
                    'x_reconnect_delay_ms' => 100,
                    'x_reconnect_delay_multiplier' => 2.0,
                    'x_reconnect_logging' => true,
                ],
            ],
            $driver->reveal(),
            $this->mockConfiguration(),
        );

        // We need to create a reflection class to access protected properties
        $reflection = new \ReflectionClass($connection);

        $baseRetryDelayMs = $reflection->getProperty('baseRetryDelayMs');
        $baseRetryDelayMs->setAccessible(true);
        $this->assertEquals(100, $baseRetryDelayMs->getValue($connection));

        $retryDelayMultiplier = $reflection->getProperty('retryDelayMultiplier');
        $retryDelayMultiplier->setAccessible(true);
        $this->assertEquals(2.0, $retryDelayMultiplier->getValue($connection));

        $enableRetryLogging = $reflection->getProperty('enableRetryLogging');
        $enableRetryLogging->setAccessible(true);
        $this->assertTrue($enableRetryLogging->getValue($connection));
    }

    public function testDelayOptionsDefaultValues(): void
    {
        $driver = $this->prophesize(Driver::class);
        $connection = new Connection(
            [
                'driverOptions' => [
                    'x_reconnect_attempts' => 3,
                ],
            ],
            $driver->reveal(),
            $this->mockConfiguration(),
        );

        $reflection = new \ReflectionClass($connection);

        $baseRetryDelayMs = $reflection->getProperty('baseRetryDelayMs');
        $baseRetryDelayMs->setAccessible(true);
        $this->assertEquals(0, $baseRetryDelayMs->getValue($connection));

        $retryDelayMultiplier = $reflection->getProperty('retryDelayMultiplier');
        $retryDelayMultiplier->setAccessible(true);
        $this->assertEquals(1.0, $retryDelayMultiplier->getValue($connection));

        $enableRetryLogging = $reflection->getProperty('enableRetryLogging');
        $enableRetryLogging->setAccessible(true);
        $this->assertFalse($enableRetryLogging->getValue($connection));
    }

    #[DataProvider('invalidDelayOptionsDataProvider')]
    public function testInvalidDelayOptionsValidation(mixed $invalidValue, string $errorMessage, string $optionName): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ' . $optionName . ' option: ' . $errorMessage);

        $driver = $this->prophesize(Driver::class);
        $driverOptions = [
            'x_reconnect_attempts' => 3,
            $optionName => $invalidValue, // Psalm doesn't like dynamic keys
        ];

        new Connection(
            [
                'driverOptions' => $driverOptions,
            ],
            $driver->reveal(),
            $this->mockConfiguration(),
        );
    }

    public static function invalidDelayOptionsDataProvider(): array
    {
        return [
            ['not_numeric', 'expecting int/float, got string', 'x_reconnect_delay_ms'],
            [-50, 'it must not be negative', 'x_reconnect_delay_ms'],
            ['string', 'expecting int/float, got string', 'x_reconnect_delay_multiplier'],
            [0.5, 'it must be >= 1.0', 'x_reconnect_delay_multiplier'],
            [-1.0, 'it must be >= 1.0', 'x_reconnect_delay_multiplier'],
        ];
    }

    public function testExponentialBackoffCalculation(): void
    {
        // This test checks the internal calculation logic
        $driver = $this->prophesize(Driver::class);
        $connection = new Connection(
            [
                'driverOptions' => [
                    'x_reconnect_attempts' => 3,
                    'x_reconnect_delay_ms' => 10,
                    'x_reconnect_delay_multiplier' => 2.0,
                ],
            ],
            $driver->reveal(),
            $this->mockConfiguration(),
        );

        // Use reflection to access the private calculateRetryDelay method
        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('calculateRetryDelay');
        $method->setAccessible(true);

        // Also access the properties to set values for testing
        $baseRetryDelayMs = $reflection->getProperty('baseRetryDelayMs');
        $baseRetryDelayMs->setAccessible(true);
        $baseRetryDelayMs->setValue($connection, 10);

        $retryDelayMultiplier = $reflection->getProperty('retryDelayMultiplier');
        $retryDelayMultiplier->setAccessible(true);
        $retryDelayMultiplier->setValue($connection, 2.0);

        $currentAttempts = $reflection->getProperty('currentAttempts');
        $currentAttempts->setAccessible(true);

        // Test attempt 0 (first retry): 10 * (2.0 ^ 0) = 10
        $currentAttempts->setValue($connection, 0);
        $this->assertEquals(10, $method->invoke($connection));

        // Test attempt 1 (second retry): 10 * (2.0 ^ 1) = 20
        $currentAttempts->setValue($connection, 1);
        $this->assertEquals(20, $method->invoke($connection));

        // Test attempt 2 (third retry): 10 * (2.0 ^ 2) = 40
        $currentAttempts->setValue($connection, 2);
        $this->assertEquals(40, $method->invoke($connection));
    }

    public function testFixedDelayCalculation(): void
    {
        $driver = $this->prophesize(Driver::class);
        $connection = new Connection(
            [
                'driverOptions' => [
                    'x_reconnect_attempts' => 3,
                    'x_reconnect_delay_ms' => 50,
                    'x_reconnect_delay_multiplier' => 1.0, // Fixed delay
                ],
            ],
            $driver->reveal(),
            $this->mockConfiguration(),
        );

        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('calculateRetryDelay');
        $method->setAccessible(true);

        $baseRetryDelayMs = $reflection->getProperty('baseRetryDelayMs');
        $baseRetryDelayMs->setAccessible(true);
        $baseRetryDelayMs->setValue($connection, 50);

        $retryDelayMultiplier = $reflection->getProperty('retryDelayMultiplier');
        $retryDelayMultiplier->setAccessible(true);
        $retryDelayMultiplier->setValue($connection, 1.0);

        $currentAttempts = $reflection->getProperty('currentAttempts');
        $currentAttempts->setAccessible(true);

        // With multiplier 1.0, all attempts should return 50ms
        for ($i = 0; $i < 5; ++$i) {
            $currentAttempts->setValue($connection, $i);
            $this->assertEquals(50, $method->invoke($connection));
        }
    }

    public function testDelayMaxLimit(): void
    {
        // Test that the delay is capped at 60,000ms (60 seconds)
        $driver = $this->prophesize(Driver::class);
        $connection = new Connection(
            [
                'driverOptions' => [
                    'x_reconnect_attempts' => 3,
                    'x_reconnect_delay_ms' => 100,
                    'x_reconnect_delay_multiplier' => 10.0, // Very high multiplier
                ],
            ],
            $driver->reveal(),
            $this->mockConfiguration(),
        );

        $reflection = new \ReflectionClass($connection);
        $method = $reflection->getMethod('calculateRetryDelay');
        $method->setAccessible(true);

        $baseRetryDelayMs = $reflection->getProperty('baseRetryDelayMs');
        $baseRetryDelayMs->setAccessible(true);
        $baseRetryDelayMs->setValue($connection, 100);

        $retryDelayMultiplier = $reflection->getProperty('retryDelayMultiplier');
        $retryDelayMultiplier->setAccessible(true);
        $retryDelayMultiplier->setValue($connection, 10.0);

        $currentAttempts = $reflection->getProperty('currentAttempts');
        $currentAttempts->setAccessible(true);

        // Even with high multiplier, should be capped at 60,000ms
        $currentAttempts->setValue($connection, 10); // 100 * (10^10) would be huge without cap
        $this->assertLessThanOrEqual(60_000, $method->invoke($connection));
        $this->assertEquals(60_000, $method->invoke($connection)); // Should be capped
    }
}
