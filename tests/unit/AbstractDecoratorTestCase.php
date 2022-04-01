<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use PHPUnit\Framework\TestCase;

abstract class AbstractDecoratorTestCase extends TestCase
{
    /**
     * @return class-string
     */
    abstract protected function getClassUnderTest(): string;

    public function testAllParentMethodsAreDecorated(): void
    {
        $classUnderTest = $this->getClassUnderTest();

        $connection = new \ReflectionClass($classUnderTest);
        $missingMethods = [];

        foreach ($this->getPublicNonFinalMethods($connection->getParentClass()->getName()) as $methodName) {
            $method = $connection->getMethod($methodName);

            if ($classUnderTest !== $method->getDeclaringClass()->getName()) {
                $missingMethods[] = $methodName;
            }
        }

        $this->assertEmpty($missingMethods, 'Some methods are not decorated: ' . PHP_EOL . implode(PHP_EOL, $missingMethods));
    }

    /**
     * @param class-string $className
     *
     * @return list<string>
     */
    private function getPublicNonFinalMethods(string $className): array
    {
        $dbalClass = new \ReflectionClass($className);

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
