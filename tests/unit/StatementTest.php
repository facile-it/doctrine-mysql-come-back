<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Prophecy\PhpUnit\ProphecyTrait;

class StatementTest extends AbstractDecoratorTestCase
{
    use ProphecyTrait;

    protected function getClassUnderTest(): string
    {
        return Statement::class;
    }
}
