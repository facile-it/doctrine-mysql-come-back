<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection as DBALPrimaryReadReplicaConnection;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionTrait;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionInterface;

/**
 * Class PrimaryReadReplicaConnection
 */
class PrimaryReadReplicaConnection extends DBALPrimaryReadReplicaConnection implements ConnectionInterface
{
    use ConnectionTrait;
}
