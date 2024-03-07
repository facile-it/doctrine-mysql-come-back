<?php

namespace Facile\DoctrineMySQLComeBack\Tests\Unit;

use Doctrine\DBAL\Configuration;
use Facile\DoctrineMySQLComeBack\Tests\DeprecationTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

abstract class BaseUnitTestCase extends TestCase
{
    use ProphecyTrait;
    use DeprecationTrait;

    protected function mockConfiguration(): Configuration
    {
        $configuration = $this->prophesize(Configuration::class);
        $configuration->getSchemaManagerFactory()
            ->willReturn();
        $configuration->getAutoCommit()
            ->willReturn(false);
        $configuration->getDisableTypeComments()
            ->willReturn(false);

        return $configuration->reveal();
    }
}
