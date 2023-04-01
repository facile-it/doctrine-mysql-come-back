<?php

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\ConnectionTrait;

class PrimaryReadReplicaConnection extends \Doctrine\DBAL\Connections\PrimaryReadReplicaConnection
{
    use ConnectionTrait {
        __construct as __traitConstruct;
    }

    public function __construct(array $params, Driver $driver, ?Configuration $config = null, ?EventManager $eventManager = null)
    {
        if (isset($params['primary']['driverOptions']['x_reconnect_attempts'])) {
            $params['driverOptions']['x_reconnect_attempts'] = $params['primary']['driverOptions']['x_reconnect_attempts'];
            unset($params['primary']['driverOptions']['x_reconnect_attempts']);
        }

        self::__traitConstruct($params, $driver, $config, $eventManager);
    }
}
