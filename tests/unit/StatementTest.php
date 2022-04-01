<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class StatementTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @dataProvider publicMethodsDataProvider
     */
    public function testAllParentMethodsAreDecorated(string $methodName): void
    {
        $connection = new \ReflectionClass(Statement::class);
        $method = $connection->getMethod($methodName);

        $this->assertEquals($connection, $method->getDeclaringClass(), 'Method not decorated: ' . $method->getName());
    }

    public function publicMethodsDataProvider(): \Generator
    {
        $dbalClass = new \ReflectionClass(\Doctrine\DBAL\Statement::class);

        foreach ($dbalClass->getMethods() as $method) {
            if (! $method->isPublic()) {
                continue;
            }

            yield [$method->getName()];
        }
    }
}
