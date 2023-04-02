<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement as DBALStatement;
use Doctrine\DBAL\Types\Type;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\GoneAwayDetector;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\MySQLGoneAwayDetector;

/**
 * @psalm-require-extends \Doctrine\DBAL\Connection
 */
trait ConnectionTrait
{
    protected GoneAwayDetector $goneAwayDetector;

    protected int $reconnectAttempts = 0;

    private ?\ReflectionProperty $selfReflectionNestingLevelProperty = null;

    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null,
        ?EventManager $eventManager = null
    ) {
        if (isset($params['driverOptions']['x_reconnect_attempts'])) {
            $this->reconnectAttempts = $this->validateAttemptsOption($params['driverOptions']['x_reconnect_attempts']);
            unset($params['driverOptions']['x_reconnect_attempts']);
        }

        $this->goneAwayDetector = new MySQLGoneAwayDetector();

        /**
         * @psalm-suppress InternalMethod
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @param mixed $attempts
     */
    protected function validateAttemptsOption($attempts): int
    {
        if (! is_int($attempts)) {
            throw new \InvalidArgumentException('Invalid x_reconnect_attempts option: expecting int, got ' . gettype($attempts));
        }

        if ($attempts < 0) {
            throw new \InvalidArgumentException('Invalid x_reconnect_attempts option, it must not be negative');
        }

        return $attempts;
    }

    public function setGoneAwayDetector(GoneAwayDetector $goneAwayDetector): void
    {
        $this->goneAwayDetector = $goneAwayDetector;
    }

    /**
     * @template R
     *
     * @param callable():R $callable
     *
     * @return R
     */
    private function doWithRetry(callable $callable, string $sql = null, bool $tolerateOneTransactionLevel = false)
    {
        $attempt = 0;

        do {
            try {
                return $callable();
            } catch (\Exception $e) {
                if (! $this->canTryAgain($e, $attempt, $sql, $tolerateOneTransactionLevel)) {
                    throw $e;
                }

                $this->close();
                ++$attempt;
            }
        } while (true);
    }

    public function prepare(string $sql): DBALStatement
    {
        // Mysqli executes statement on Statement constructor, so we should retry to reconnect here too
        return $this->doWithRetry(function () use ($sql): Statement {
            /** @psalm-suppress InternalMethod */
            $this->connect();

            /**
             * @psalm-suppress InternalMethod
             * @psalm-suppress PossiblyNullReference
             */
            $driverStatement = @$this->_conn->prepare($sql);

            return new Statement($this, $driverStatement, $sql);
        });
    }

    /**
     * @param string $sql
     * @param list<mixed>|array<string, mixed>                                     $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     */
    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        return $this->doWithRetry(function () use ($sql, $params, $types, $qcp): Result {
            return @parent::executeQuery($sql, $params, $types, $qcp);
        }, $sql);
    }

    /**
     * @param string $sql
     * @param list<mixed>|array<string, mixed>                                     $params
     * @param array<int, int|string|Type|null>|array<string, int|string|Type|null> $types
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function executeStatement($sql, array $params = [], array $types = [])
    {
        return $this->doWithRetry(function () use ($sql, $params, $types) {
            return @parent::executeStatement($sql, $params, $types);
        }, $sql);
    }

    public function beginTransaction()
    {
        if (0 !== $this->getTransactionNestingLevel()) {
            return @parent::beginTransaction();
        }

        return $this->doWithRetry(function (): bool {
            return parent::beginTransaction();
        }, null, true);
    }

    public function canTryAgain(\Throwable $throwable, int $attempt, string $sql = null, bool $tolerateOneTransactionLevel = false): bool
    {
        if ($attempt >= $this->reconnectAttempts) {
            return false;
        }

        $toleratedLevel = 0;
        if ($tolerateOneTransactionLevel) {
            $toleratedLevel = 1;
        }

        if ($this->getTransactionNestingLevel() > $toleratedLevel) {
            return false;
        }

        return $this->goneAwayDetector->isGoneAwayException($throwable, $sql);
    }
}
