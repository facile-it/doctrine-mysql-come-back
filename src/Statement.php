<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Result;
use Exception;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;

/**
 * @internal
 */
class Statement extends \Doctrine\DBAL\Statement
{
    /** @var Connection|PrimaryReadReplicaConnection */
    protected \Doctrine\DBAL\Connection $retriableConnection;

    /**
     * @param Connection|PrimaryReadReplicaConnection $retriableConnection
     */
    public function __construct(\Doctrine\DBAL\Connection $retriableConnection, Driver\Statement $statement, string $sql)
    {
        /** @psalm-suppress InternalMethod */
        parent::__construct($retriableConnection, $statement, $sql);

        $this->retriableConnection = $retriableConnection;
    }

    /**
     * Recreates the statement for retry.
     */
    private function recreateStatement(): void
    {
        $this->stmt = $this->conn->getWrappedConnection()->prepare($this->sql);
    }

    public function executeQuery(array $params = []): Result
    {
        return $this->executeWithRetry([parent::class, 'executeQuery'], $params);
    }

    public function executeStatement(array $params = []): int
    {
        return $this->executeWithRetry([parent::class, 'executeStatement'], $params);
    }

    private function executeWithRetry($callable, ...$params)
    {
        $parentCall = \Closure::fromCallable($callable);
        $parentCall->bindTo($this, parent::class);

        $attempt = 0;

        do {
            try {
                return $parentCall(...$params);
            } catch (Exception $e) {
                if ($this->retriableConnection->canTryAgain($e, $attempt, $this->sql)) {
                    $this->retriableConnection->close();
                    $this->recreateStatement();
                    ++$attempt;
                } else {
                    throw $e;
                }
            }
        } while (true);
    }
}
