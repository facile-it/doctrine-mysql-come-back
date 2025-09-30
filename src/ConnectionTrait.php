<?php

declare(strict_types=1);

namespace Facile\DoctrineMySQLComeBack\Doctrine\DBAL;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement as DBALStatement;
use Doctrine\DBAL\Types\Type;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\GoneAwayDetector;
use Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Detector\MySQLGoneAwayDetector;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * @psalm-require-extends Connection
 *
 * @psalm-type WrapperParameterType = string|Type|ParameterType|ArrayParameterType
 * @psalm-type WrapperParameterTypeArray = array<int<0, max>, WrapperParameterType>|array<string, WrapperParameterType>
 */
trait ConnectionTrait
{
    use LoggerAwareTrait;

    protected GoneAwayDetector $goneAwayDetector;

    protected int $maxReconnectAttempts = 0;

    protected int $currentAttempts = 0;

    private bool $hasBeenClosedWithAnOpenTransaction = false;

    private bool $currentlyOpeningFirstLevelTransaction = false;

    private ?\ReflectionProperty $selfReflectionNestingLevelProperty = null;

    // New properties for retry delay functionality
    protected int $baseRetryDelayMs = 0;

    protected float $retryDelayMultiplier = 1.0;

    protected bool $enableRetryLogging = false;

    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null
    ) {
        $this->commonConstructor($params, $driver, $config);
    }

    private function commonConstructor(array &$params, Driver $driver, ?Configuration $config): void
    {
        if (isset($params['driverOptions']['x_reconnect_attempts'])) {
            $this->maxReconnectAttempts = $this->validateAttemptsOption($params['driverOptions']['x_reconnect_attempts']);
            unset($params['driverOptions']['x_reconnect_attempts']);
        }

        // New driver options for retry delay functionality
        if (isset($params['driverOptions']['x_reconnect_delay_ms'])) {
            $this->baseRetryDelayMs = $this->validateDelayOption($params['driverOptions']['x_reconnect_delay_ms']);
            unset($params['driverOptions']['x_reconnect_delay_ms']);
        }

        if (isset($params['driverOptions']['x_reconnect_delay_multiplier'])) {
            $this->retryDelayMultiplier = $this->validateMultiplierOption($params['driverOptions']['x_reconnect_delay_multiplier']);
            unset($params['driverOptions']['x_reconnect_delay_multiplier']);
        }

        if (isset($params['driverOptions']['x_reconnect_logging'])) {
            $this->enableRetryLogging = (bool) $params['driverOptions']['x_reconnect_logging'];
            unset($params['driverOptions']['x_reconnect_logging']);
        }

        $this->goneAwayDetector = new MySQLGoneAwayDetector();

        /**
         * @psalm-suppress InternalMethod
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        parent::__construct($params, $driver, $config);
    }

    private function validateAttemptsOption(mixed $attempts): int
    {
        if (! is_int($attempts)) {
            throw new \InvalidArgumentException('Invalid x_reconnect_attempts option: expecting int, got ' . gettype($attempts));
        }

        if ($attempts < 0) {
            throw new \InvalidArgumentException('Invalid x_reconnect_attempts option: it must not be negative');
        }

        return $attempts;
    }

    private function validateDelayOption(mixed $delay): int
    {
        if (! is_int($delay) && ! is_float($delay)) {
            throw new \InvalidArgumentException('Invalid x_reconnect_delay_ms option: expecting int/float, got ' . gettype($delay));
        }

        $delay = (int) $delay;

        if ($delay < 0) {
            throw new \InvalidArgumentException('Invalid x_reconnect_delay_ms option: it must not be negative');
        }

        return $delay;
    }

    private function validateMultiplierOption(mixed $multiplier): float
    {
        if (! is_int($multiplier) && ! is_float($multiplier)) {
            throw new \InvalidArgumentException('Invalid x_reconnect_delay_multiplier option: expecting int/float, got ' . gettype($multiplier));
        }

        $multiplier = (float) $multiplier;

        if ($multiplier < 1.0) {
            throw new \InvalidArgumentException('Invalid x_reconnect_delay_multiplier option: it must be >= 1.0');
        }

        return $multiplier;
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
    private function doWithRetry(callable $callable, ?string $sql = null)
    {
        try {
            attempt:
            $result = $callable();
        } catch (\Throwable $e) {
            if (! $this->canTryAgain($e, $sql)) {
                throw $e;
            }

            $this->close();
            $this->increaseAttemptCount();

            // Calculate and apply delay before retry
            if ($this->baseRetryDelayMs > 0) {
                $delayMs = $this->calculateRetryDelay();

                // Log retry attempt if logging is enabled
                if ($this->enableRetryLogging) {
                    $this->logRetryAttempt($e, $sql, $delayMs);
                }

                $this->applyDelay($delayMs);
            }

            goto attempt;
        }

        $this->resetAttemptCount();

        /** @psalm-suppress PossiblyUndefinedVariable */
        return $result;
    }

    /**
     * Calculate the delay for the current retry attempt using exponential backoff.
     */
    private function calculateRetryDelay(): int
    {
        // Calculate delay as: base_delay * (multiplier ^ attempt_number)
        // Where attempt_number starts from 0
        $calculatedDelay = (float) $this->baseRetryDelayMs * pow($this->retryDelayMultiplier, $this->currentAttempts);

        // Apply a reasonable maximum to prevent excessive delays
        $actualDelay = min((int) $calculatedDelay, 60_000);

        return $actualDelay;
    }

    /**
     * Apply the delay using usleep (convert milliseconds to microseconds).
     */
    private function applyDelay(int $delayMs): void
    {
        if ($delayMs > 0) {
            usleep($delayMs * 1_000); // Convert milliseconds to microseconds
        }
    }

    /**
     * Log retry attempt if logging is enabled.
     */
    private function logRetryAttempt(\Throwable $exception, ?string $sql, int $delayMs): void
    {
        if ($this->enableRetryLogging && $this->logger instanceof LoggerInterface) {
            $this->logger->debug('MySQL reconnect attempt', [
                'attempt' => $this->currentAttempts + 1,
                'delay_ms' => $delayMs,
                'exception_message' => $exception->getMessage(),
                'query' => $sql ?? 'N/A',
                'exception_class' => $exception::class,
            ]);
        }
    }

    /**
     * @internal
     */
    public function increaseAttemptCount(): void
    {
        ++$this->currentAttempts;
    }

    /**
     * @internal
     */
    public function resetAttemptCount(): void
    {
        $this->currentAttempts = 0;
    }

    public function connect(?string $connectionName = null): DriverConnection
    {
        $this->hasBeenClosedWithAnOpenTransaction = false;

        /** @psalm-suppress InternalMethod */
        return parent::connect($connectionName);
    }

    public function close(): void
    {
        if ($this->getTransactionNestingLevel() > 0) {
            $this->hasBeenClosedWithAnOpenTransaction = true;
        }

        parent::close();
    }

    public function prepare(string $sql): DBALStatement
    {
        return $this->doWithRetry(function () use ($sql): Statement {
            $dbalStatement = parent::prepare($sql);

            return Statement::fromDBALStatement($this, $dbalStatement);
        });
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @psalm-param WrapperParameterTypeArray $types
     */
    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        return $this->doWithRetry(fn(): Result => parent::executeQuery($sql, $params, $types, $qcp), $sql);
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @psalm-param WrapperParameterTypeArray $types
     *
     * @return int|numeric-string
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        return $this->doWithRetry(fn() => parent::executeStatement($sql, $params, $types), $sql);
    }

    public function beginTransaction(): void
    {
        if ($this->getTransactionNestingLevel() === 0) {
            $this->currentlyOpeningFirstLevelTransaction = true;
        }

        $this->doWithRetry(function (): void {
            parent::beginTransaction();
        });

        $this->currentlyOpeningFirstLevelTransaction = false;
    }

    public function canTryAgain(\Throwable $throwable, ?string $sql = null): bool
    {
        if ($this->hasBeenClosedWithAnOpenTransaction && ! $this->currentlyOpeningFirstLevelTransaction) {
            return false;
        }

        if ($this->currentAttempts >= $this->maxReconnectAttempts) {
            return false;
        }

        return $this->goneAwayDetector->isGoneAwayException($throwable, $sql);
    }
}
