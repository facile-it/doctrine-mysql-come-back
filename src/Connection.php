<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

/** @psalm-suppress PropertyNotSetInConstructor */
class Connection extends \Doctrine\DBAL\Connection
{
    use ConnectionTrait;
}
