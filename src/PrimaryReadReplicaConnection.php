<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

class PrimaryReadReplicaConnection extends \Doctrine\DBAL\Connections\PrimaryReadReplicaConnection
{
    use ConnectionTrait;
}
