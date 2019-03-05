<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Connection as DBALConnection;

/**
 * Class Connection.
 */
class Connection extends DBALConnection implements ConnectionInterface
{
    use ConnectionTrait;
}
