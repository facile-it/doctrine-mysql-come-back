<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection as DBALPrimaryReadReplicaConnection;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionInterface;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionTrait;

/**
 * Class PrimaryReadReplicaConnection
 */
class PrimaryReadReplicaConnection extends DBALPrimaryReadReplicaConnection implements ConnectionInterface
{
    use ConnectionTrait;
}
