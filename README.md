[![Latest Stable Version](https://poser.pugx.org/facile-it/doctrine-mysql-come-back/v/stable.svg)](https://packagist.org/packages/facile-it/doctrine-mysql-come-back) 
[![Latest Unstable Version](https://poser.pugx.org/facile-it/doctrine-mysql-come-back/v/unstable.svg)](https://packagist.org/packages/facile-it/doctrine-mysql-come-back) 
[![Total Downloads](https://poser.pugx.org/facile-it/doctrine-mysql-come-back/downloads.svg)](https://packagist.org/packages/facile-it/doctrine-mysql-come-back) 

[![Build status](https://github.com/facile-it/doctrine-mysql-come-back/workflows/Continuous%20Integration/badge.svg)]( https://github.com/facile-it/doctrine-mysql-come-back/actions?query=workflow%3A%22Continuous+Integration%22+branch%3Amaster)
[![Test coverage](https://codecov.io/gh/facile-it/doctrine-mysql-come-back/branch/master/graph/badge.svg?token=vFz9cWGQ3r)](https://codecov.io/gh/facile-it/doctrine-mysql-come-back)

[![License](https://poser.pugx.org/facile-it/doctrine-mysql-come-back/license.svg)](https://packagist.org/packages/facile-it/doctrine-mysql-come-back)
# DoctrineMySQLComeBack

Auto reconnect on Doctrine MySql has gone away exceptions on `doctrine/dbal`.

# Installation

If you're using DBAL 3.6+
```console
$ composer require facile-it/doctrine-mysql-come-back ^2.0
```

If you're using DBAL `^2.3`
```console
$ composer require facile-it/doctrine-mysql-come-back ^1.0
```

# Configuration

In order to use DoctrineMySQLComeBack you have to set the `wrapperClass` connection parameter.
You can choose how many times Doctrine should be able to reconnect, setting `x_reconnect_attempts` driver option. Its value should be an int.

If you're using DBAL v2, you also need to set the `driverClass` parameter too; please refer to the [previous version of this readme](https://github.com/facile-it/doctrine-mysql-come-back/blob/1.10.1/README.md#configuration) for that.

An example of configuration at connection instantiation time:

```php
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

$config = new Configuration();

//..

$connectionParams = [
    'dbname' => 'mydb',
    'user' => 'user',
    'password' => 'secret',
    'host' => 'localhost',
    // [doctrine-mysql-come-back] settings
    'wrapperClass' => 'Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection',
    'driverOptions' => [
        'x_reconnect_attempts' => 3
    ],
];

$conn = DriverManager::getConnection($connectionParams, $config);

//..
```

An example of yaml configuration on Symfony projects:

```yaml
doctrine:
    dbal:
        connections:
            default:
                # DATABASE_URL would be of "mysql://db_user:db_password@127.0.0.1:3306/db_name" 
                url: '%env(resolve:DATABASE_URL)%'
                wrapper_class: 'Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection'
                options:
                    x_reconnect_attempts: 3
``` 

An example of configuration on Laminas Framework 2projects:

```php
return [
    'doctrine' => [
        'connection' => [
            'orm_default' => [
                'wrapperClass' => \Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection::class,
                'params' => [
                    'host' => 'localhost',
                    'port' => '3307',
                    'user' => '##user##',
                    'password' => '##password##',
                    'dbname' => '##database##',
                    'charset' => 'UTF8',
                    'driverOptions' => [
                        'x_reconnect_attempts' => 9,
                    ]
                ],
            ],
        ],
    ],
];
```

You can use wrapper class `Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\PrimaryReadReplicaConnection` if you are using a primary/replica Doctrine configuration:
```php
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

$config = new Configuration();

//..

$connectionParams = [
    'wrapperClass' => 'Facile\DoctrineMySQLComeBack\Doctrine\DBAL\PrimaryReadReplicaConnection',
    'primary' => [
        // ...
        'driverOptions' => [
            'x_reconnect_attempts' => 3
        ],
    ],   
];

$conn = DriverManager::getConnection($connectionParams, $config);

//..
```

# Usage

Since DBAL v3, `Connection::refresh` does not exist anymore, so you don't need to do anything else to leverage the reconnection, it will be automagically done.

From `v1.6` of this library automagically reconnection is enabled also during `$em->getConnection()->beginTransaction()` calls,
and this works also during simple `$em->flush()`, if out of a previous transaction.

# Thanks
Thanks to Dieter Peeters and his proposal on [DBAL-275](https://github.com/doctrine/dbal/issues/1454).
Check it out if you are using doctrine/dbal <2.3.
