<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Exception;

interface ConnectionInterface extends DriverConnection
{
    public function canTryAgain(int $attempt, bool $ignoreTransactionLevel = false): bool;

    public function isRetryableException(Exception $e, ?string $query = null): bool;

    public function isUpdateQuery(string $query): bool;
}
