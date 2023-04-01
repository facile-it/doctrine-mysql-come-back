<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections;

use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionTrait;

class PrimaryReadReplicaConnection extends \Doctrine\DBAL\Connections\PrimaryReadReplicaConnection
{
    use ConnectionTrait;
}
