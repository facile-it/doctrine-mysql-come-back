<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections;

use Doctrine\DBAL\Connections\MasterSlaveConnection as DBALMasterSlaveConnection;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionInterface;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionTrait;

/**
 * Class MasterSlaveConnection
 *
 * @deprecated Use PrimaryReadReplicaConnection instead
 */
class MasterSlaveConnection extends DBALMasterSlaveConnection implements ConnectionInterface
{
    use ConnectionTrait;
}
