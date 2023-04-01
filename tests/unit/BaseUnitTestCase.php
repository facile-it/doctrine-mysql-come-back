<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Configuration;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class BaseUnitTestCase extends TestCase
{
    use ProphecyTrait;

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
