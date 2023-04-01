<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Configuration;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class BaseUnitTestCase extends TestCase
{
    use ProphecyTrait;

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
