<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Result;
use Exception;

/**
 * @internal
 */
class Statement extends \Doctrine\DBAL\Statement
{
    /** @var Connection */
    protected $retriableConnection;

    public function __construct(Connection $retriableConnection, Driver\Statement $statement, string $sql)
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

//    public function execute($params = null): Result
//    {
//        return $this->executeWithRetry([parent::class, 'execute'], $params);
//    }

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
            $retry = false;
            try {
                return $parentCall(...$params);
            } catch (Exception $e) {
                if ($this->retriableConnection->canTryAgain($e, $attempt, $this->sql)) {
                    $this->retriableConnection->close();
                    $this->recreateStatement();
                    ++$attempt;
                    $retry = true;
                } else {
                    throw $e;
                }
            }
        } while ($retry);
    }
}
